<?php

namespace App\Filament\Tenant\Resources\WorkflowResource\Pages;

use App\Filament\Tenant\Resources\WorkflowResource;
use App\Models\Workflow;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Contracts\HasForms;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class ViewLogs extends Page implements HasForms
{
    protected static string $resource = WorkflowResource::class;

    protected static string $view = 'filament.resources.workflow-resource.pages.view-logs';

    public Workflow $record;
    public string $logs, $execution_logs;

    public function getHeading(): string|Htmlable
    {
        return "Workflow #" . $this->record->id;
    }

    public function getSubheading(): string|Htmlable|null
    {
        $sub_heading = "#" . $this->record->description;
        $actions = implode(', ', str_replace('-', ' ', $this->record->actions->pluck('action')->toArray()));;
        return new HtmlString($sub_heading . "<br> <strong>$actions</strong");
    }


    public function mount()
    {
        $this->record = \Route::current()->parameter('record');
        $logs = $this->record->logs ?? [];
        $executionLogs = $this->record->executions->pluck('logs')->flatten()->toArray();

        $this->logs = implode("\n\n", $logs);
        $this->execution_logs = implode("\n\n", $executionLogs);


        if (empty($this->logs))
            $this->logs = "No logs yet.";

        if (empty($this->execution_logs))
            $this->execution_logs = "No logs yet.";

    }

    protected function getFormSchema(): array
    {
        return [
            Card::make([
                Textarea::make('logs')
                    ->rows(10)
                    ->cols(20)
                    ->disabled(),

                Textarea::make('execution_logs')
                    ->rows(10)
                    ->cols(20)
                    ->disabled(),
            ])
        ];
    }
}
