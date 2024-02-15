<?php

namespace TomShaw\ElectricGrid\Traits;

use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use TomShaw\ElectricGrid\{DataExport, DataSource};

trait WithMassActions
{
    public string $selectedAction;

    public function handleSelectedAction(): Response|BinaryFileResponse|null
    {
        $where = collect($this->actions())->flatten()->where('field', $this->selectedAction);

        if ($where->isEmpty() || empty($this->checkboxValues)) {
            return null;
        }

        $action = collect((array) $where->first());

        $columns = $this->columns();

        if ($action->get('isExport')) {
            $exportables = collect($columns)->filter->exportable;

            if ($exportables->isEmpty()) {
                return null;
            }

            $dataSource = DataSource::make($this->builder());

            $dataSource->query->whereIn("{$dataSource->query->from}.{$this->checkboxField}", $this->checkboxValues);

            $columns = $dataSource->transformColumns($exportables->toArray());

            $collection = $dataSource->transformCollection($dataSource->query->get(), $columns);

            $action->put('headings', $exportables->pluck('title')->toArray());

            return $this->export($collection, $action);
        }

        if ($action->has('callable') && is_callable($action->get('callable'))) {
            $callable = $action->get('callable');
            $callable($this->selectedAction, $this->checkboxValues);
        }

        return null;
    }

    public function export(Collection $collection, Collection $action): Response|BinaryFileResponse
    {
        $export = new DataExport($collection);

        $export->setHeadings($action->get('headings'));

        $export->setFileName($action->get('fileName'));

        $export->setStyles($action->get('styles'));

        $export->setColumnWidths($action->get('columnWidths'));

        return $export->download();
    }
}
