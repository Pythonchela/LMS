<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CouponResource\Pages;
use App\Models\Coupon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

/** Discount codes students type at checkout. */
class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    protected static string|\UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Coupons';

    public static function canViewAny(): bool
    {
        $admin = auth('admin')->user();

        return (bool) $admin && ($admin->isSuperAdmin() || $admin->hasPermission('manage_payments'));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('The code')->schema([
                TextInput::make('code')->required()->maxLength(40)->unique(ignoreRecord: true)
                    ->helperText('What the student types, e.g. WELCOME20. Capitals and spaces do not matter.'),
                TextInput::make('description')->maxLength(190)->helperText('For your own reference only.'),
                Select::make('type')->options(['percent' => 'Percent off (%)', 'fixed' => 'Fixed amount off (₹)'])->default('percent')->required()->live(),
                TextInput::make('value')->numeric()->required()->minValue(0.01)
                    ->maxValue(fn ($get) => $get('type') === 'percent' ? 100 : 10000000)
                    ->suffix(fn ($get) => $get('type') === 'percent' ? '%' : '₹'),
                TextInput::make('max_discount')->numeric()->prefix('₹')->visible(fn ($get) => $get('type') === 'percent')
                    ->helperText('Optional cap, e.g. 20% off but never more than ₹500.'),
            ])->columns(2),
            Section::make('Rules')->schema([
                Select::make('batch_id')->label('Only for this batch')->relationship('batch', 'title')->searchable()->preload()->placeholder('Any batch'),
                TextInput::make('min_order')->label('Minimum price (₹)')->numeric()->prefix('₹'),
                TextInput::make('max_uses')->label('Total uses allowed')->numeric()->minValue(1)->helperText('Leave empty for unlimited.'),
                TextInput::make('per_user_limit')->label('Uses per student')->numeric()->minValue(1)->default(1),
                DateTimePicker::make('starts_at')->label('Valid from'),
                DateTimePicker::make('expires_at')->label('Valid until'),
                Toggle::make('is_active')->label('Active')->default(true)->inline(false),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('code')->searchable()->weight('bold')->copyable(),
                TextColumn::make('value')->label('Discount')
                    ->formatStateUsing(fn (Coupon $r) => $r->type === 'percent' ? rtrim(rtrim(number_format((float) $r->value, 2), '0'), '.').'%' : '₹'.number_format((float) $r->value)),
                TextColumn::make('batch.title')->label('Batch')->placeholder('Any batch')->limit(28),
                TextColumn::make('used_count')->label('Used')->formatStateUsing(fn (Coupon $r) => $r->used_count.($r->max_uses ? ' / '.$r->max_uses : ''))->badge()->color('gray'),
                TextColumn::make('expires_at')->label('Valid until')->dateTime('d M Y')->placeholder('No end date'),
                ToggleColumn::make('is_active')->label('Active'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCoupons::route('/'),
            'create' => Pages\CreateCoupon::route('/create'),
            'edit' => Pages\EditCoupon::route('/{record}/edit'),
        ];
    }
}
