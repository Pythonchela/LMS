<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Models\ActivityLog;
use App\Models\Payment;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Every checkout: who paid what, for which batch, with which coupon. Payments are never edited here, only looked at and refunds recorded. */
class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|\UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Payments';

    public static function canViewAny(): bool
    {
        $admin = auth('admin')->user();

        return (bool) $admin && ($admin->isSuperAdmin() || $admin->hasPermission('manage_payments'));
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    private static function rupees(?int $paise): string
    {
        return '₹'.number_format(((int) $paise) / 100, 2);
    }

    /** Read-only detail shown in the "View" window. */
    public static function form(Schema $schema): Schema
    {
        $show = fn (string $name, string $label, ?callable $format = null) => TextInput::make($name)->label($label)->disabled()->dehydrated(false)
            ->formatStateUsing($format ?? fn ($state) => $state);

        return $schema->components([
            Section::make('Payment')->schema([
                $show('receipt_no', 'Receipt no.'),
                $show('status', 'Status', fn ($state) => ucfirst((string) $state)),
                $show('item_title', 'Batch'),
                $show('customer_name', 'Student'),
                $show('customer_email', 'E-mail'),
                $show('amount_original_paise', 'Price', fn ($state) => self::rupees($state)),
                $show('discount_paise', 'Discount', fn ($state) => self::rupees($state)),
                $show('amount_paise', 'Paid', fn ($state) => self::rupees($state)),
                $show('coupon_code', 'Coupon'),
                $show('method', 'Method'),
                $show('razorpay_order_id', 'Razorpay order'),
                $show('razorpay_payment_id', 'Razorpay payment'),
                $show('paid_at', 'Paid at', fn ($state) => $state ? \Illuminate\Support\Carbon::parse($state)->format('d M Y, h:i A') : null),
                $show('failed_reason', 'Failure reason'),
                $show('refund_note', 'Refund note'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('receipt_no')->label('Receipt')->placeholder('Not paid')->searchable()->copyable(),
                TextColumn::make('customer_name')->label('Student')->description(fn (Payment $r) => $r->customer_email)->searchable(['customer_name', 'customer_email']),
                TextColumn::make('item_title')->label('Batch')->limit(30)->searchable(),
                TextColumn::make('amount_paise')->label('Amount')
                    ->formatStateUsing(fn ($state) => self::rupees($state))
                    ->description(fn (Payment $r) => $r->coupon_code ? "Coupon {$r->coupon_code}" : null),
                TextColumn::make('status')->badge()->color(fn ($state) => match ($state) {
                    Payment::PAID => 'success',
                    Payment::REFUNDED => 'warning',
                    Payment::FAILED => 'danger',
                    default => 'gray',
                })->formatStateUsing(fn ($state) => $state === Payment::CREATED ? 'Not completed' : ucfirst($state)),
                TextColumn::make('method')->placeholder('—')->toggleable(),
                TextColumn::make('paid_at')->label('Paid on')->dateTime('d M Y, h:i A')->placeholder('—')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    Payment::PAID => 'Paid', Payment::REFUNDED => 'Refunded', Payment::FAILED => 'Failed', Payment::CREATED => 'Not completed',
                ])->default(Payment::PAID),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('refund')
                    ->label('Record refund')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(fn (Payment $r) => $r->status === Payment::PAID && $r->amount_paise > 0)
                    ->modalHeading('Record a refund')
                    ->modalDescription('This only marks the payment as refunded here. Send the money back from the Razorpay dashboard (if you already did, this is just the note). The student keeps access until you remove it under Enrollments.')
                    ->form([Textarea::make('note')->label('Note (optional)')->rows(2)->maxLength(500)])
                    ->action(function (Payment $record, array $data) {
                        $admin = auth('admin')->user();
                        $record->update([
                            'status' => Payment::REFUNDED,
                            'refunded_at' => now(),
                            'refund_note' => $data['note'] ?? null,
                            'refunded_by' => $admin?->id,
                        ]);
                        ActivityLog::record(
                            event: 'payment.refunded',
                            description: "{$record->receipt_no} ({$record->customer_email}) was marked as refunded.",
                            subject: $record,
                            properties: ['payment_id' => $record->id, 'note' => $data['note'] ?? null],
                            admin: $admin,
                        );
                        Notification::make()->title('Marked as refunded')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListPayments::route('/')];
    }
}
