<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BlogPostResource\Pages;
use App\Models\BlogPost;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/** Articles shown at /blog on the student site. */
class BlogPostResource extends Resource
{
    protected static ?string $model = BlogPost::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-newspaper';

    protected static string|\UnitEnum|null $navigationGroup = 'Blog';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Blog posts';

    protected static ?string $modelLabel = 'blog post';

    public static function canViewAny(): bool
    {
        $admin = auth('admin')->user();

        return (bool) $admin && ($admin->isSuperAdmin() || $admin->hasPermission('manage_blog'));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Article')->schema([
                TextInput::make('title')->required()->maxLength(180)->live(onBlur: true)->columnSpanFull()
                    ->afterStateUpdated(fn (Set $set, ?string $state, string $operation) => $operation === 'create' ? $set('slug', Str::slug((string) $state)) : null),
                TextInput::make('slug')->maxLength(180)->helperText('The web address: /blog/your-slug. Made from the title; change it only before publishing.'),
                Select::make('blog_category_id')->label('Category')->relationship('category', 'name')->searchable()->preload()
                    ->createOptionForm([TextInput::make('name')->required()->maxLength(80)]),
                Textarea::make('excerpt')->rows(2)->maxLength(300)->columnSpanFull()
                    ->helperText('One or two lines shown on the blog list and used when the post is shared.'),
                RichEditor::make('body')->required()->columnSpanFull()
                    ->toolbarButtons([['bold', 'italic', 'underline', 'strike', 'link'], ['h2', 'h3'], ['bulletList', 'orderedList', 'blockquote', 'codeBlock'], ['attachFiles'], ['undo', 'redo']])
                    ->fileAttachmentsDisk('public')->fileAttachmentsDirectory('blog-images')
                    ->helperText('Use headings (H2, H3) to break up the text: search engines and readers both like that.'),
            ])->columns(2),

            Section::make('Cover image')->schema([
                FileUpload::make('cover_image_path')->label('Cover image')->image()->disk('public')->directory('blog-covers')->maxSize(3072),
                TextInput::make('cover_image_alt')->label('Describe the image')->maxLength(180)
                    ->helperText('A short description for people who cannot see it. Also helps search engines.'),
            ])->columns(2),

            Section::make('Publishing')->schema([
                Select::make('status')->options(['draft' => 'Draft (hidden)', 'published' => 'Published'])->default('draft')->required(),
                DateTimePicker::make('published_at')->label('Publish date')
                    ->helperText('Leave empty to publish right away. A future date schedules the post: it appears on that day.'),
                TextInput::make('author_name')->maxLength(120)->default('My Merchant Navy Team'),
            ])->columns(3),

            Section::make('Search engines (SEO)')
                ->description('Optional. Without these, the title and excerpt are used.')
                ->collapsed()
                ->schema([
                    TextInput::make('seo_title')->label('Search title')->maxLength(160)->helperText('Best under 60 characters.'),
                    Textarea::make('seo_description')->label('Search description')->rows(2)->maxLength(320)->helperText('Best 120 to 160 characters.'),
                    TextInput::make('canonical_url')->label('Canonical address')->url()->maxLength(300)
                        ->helperText('Only when this article was first published somewhere else.'),
                    Toggle::make('noindex')->label('Hide from search engines')->inline(false),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                ImageColumn::make('cover_image_path')->label('')->disk('public')->square()->size(44),
                TextColumn::make('title')->searchable()->weight('bold')->limit(60)->description(fn (BlogPost $r) => '/blog/'.$r->slug),
                TextColumn::make('category.name')->label('Category')->badge()->color('info')->placeholder('—'),
                TextColumn::make('status')->badge()->color(fn (BlogPost $r) => $r->status !== 'published' ? 'gray' : ($r->published_at?->isFuture() ? 'warning' : 'success'))
                    ->formatStateUsing(fn ($state, BlogPost $r) => $state !== 'published' ? 'Draft' : ($r->published_at?->isFuture() ? 'Scheduled' : 'Published')),
                TextColumn::make('published_at')->label('Publish date')->dateTime('d M Y')->placeholder('—')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(['draft' => 'Draft', 'published' => 'Published']),
                SelectFilter::make('blog_category_id')->label('Category')->relationship('category', 'name'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBlogPosts::route('/'),
            'create' => Pages\CreateBlogPost::route('/create'),
            'edit' => Pages\EditBlogPost::route('/{record}/edit'),
        ];
    }
}
