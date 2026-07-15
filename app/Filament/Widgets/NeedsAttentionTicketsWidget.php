<?php

namespace App\Filament\Widgets;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use Filament\Tables;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class NeedsAttentionTicketsWidget extends TableWidget
{
    protected static ?string $heading = 'Needs Attention';

    protected int | string | array $columnSpan = 'full';

    protected function getTableHeading(): string
    {
        return 'Needs Attention';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Ticket::query()
                    ->where('status', '!=', TicketStatus::Closed)
                    ->where(fn (Builder $query) => $query
                        ->whereNull('assigned_to')
                        ->orWhere('priority', 'High')
                    )
                    ->latest()
            )
            ->columns([
                TextColumn::make('ticket_number')->label('Ticket #'),
                TextColumn::make('subject')->limit(40),
                BadgeColumn::make('priority')
                    ->color(fn ($state) => $state?->filamentColor())
                    ->formatStateUsing(fn ($state) => $state?->value ?? $state),
                BadgeColumn::make('status')
                    ->color(fn ($state) => $state?->filamentColor())
                    ->formatStateUsing(fn ($state) => $state?->value ?? $state),
                TextColumn::make('assignedTo.name')
                    ->label('Assigned To')
                    ->default('Unassigned')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'gray'),
                TextColumn::make('created_at')->since(),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->url(fn (Ticket $record) => route('filament.admin.resources.tickets.view', $record))
                    ->icon('heroicon-o-eye'),
            ])
            ->paginated(false);
    }
}
