<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BlogCategoryResource\Pages;
use App\Models\BlogCategory;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Topics the blog is grouped into (each gets its own page at /blog/category/<slug>). */
class BlogCategoryResource extends Resource
{
    protected static ?string $model = BlogCategory::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|\UnitEnum|null $navigationGroup = 'Blog';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Blog categories';

    protected static ?string $modelLabel = 'blog category';

    public static function canViewAny(): bool
    {
        $admin = auth('admin')->user();

        return (bool) $admin && ($admin->isSuperAdmin() || $admin->hasPermission('manage_blog'));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(80),
            TextInput::make('slug')->maxLength(80)->helperText('Made from the name if left empty.'),
            Textarea::make('description')->rows(2)->maxLength(300)->columnSpanFull()
                ->helperText('Shown at the top of the category page and used by search engines.'),
            TextInput::make('sort_order')->numeric()->default(0),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->searchable()->weight('bold')->description(fn (BlogCategory $r) => '/blog/category/'.$r->slug),
                TextColumn::make('posts_count')->counts('posts')->label('Posts')->badge()->color('gray'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageBlogCategories::route('/')];
    }
}
