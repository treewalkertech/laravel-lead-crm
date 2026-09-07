<?php

namespace Webkul\Admin\DataGrids\Prospect;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;
use Webkul\Prospect\Repositories\ProspectContactRepository;

class ProspectDataGrid extends DataGrid
{
    /**
     * Create datagrid instance.
     *
     * @return void
     */
    public function __construct(protected ProspectContactRepository $prospectContactRepository) {}

    /**
     * Prepare query builder.
     */
    public function prepareQueryBuilder(): Builder
    {
        $queryBuilder = DB::table('prospects')
            ->leftJoin('prospect_sources', 'prospects.prospect_source_id', '=', 'prospect_sources.id')
            ->addSelect(
                'prospects.id',
                'prospects.name',
                'prospects.industry',
                'prospect_sources.name as source_name',
                'prospects.created_at'
            );

        if ($userIds = bouncer()->getAuthorizedUserIds()) {
            $queryBuilder->whereIn('prospects.user_id', $userIds);
        }

        $this->addFilter('id', 'prospects.id');

        $this->addFilter('name', 'prospects.name');

        return $queryBuilder;
    }

    /**
     * Add columns.
     */
    public function prepareColumns(): void
    {
        $this->addColumn([
            'index' => 'id',
            'label' => trans('admin::app.prospects.index.datagrid.id'),
            'type' => 'integer',
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'name',
            'label' => trans('admin::app.prospects.index.datagrid.name'),
            'type' => 'string',
            'searchable' => true,
            'sortable' => true,
            'filterable' => true,
        ]);

        $this->addColumn([
            'index' => 'industry',
            'label' => trans('admin::app.prospects.index.datagrid.industry'),
            'type' => 'string',
            'searchable' => true,
            'sortable' => true,
            'filterable' => true,
        ]);

        $this->addColumn([
            'index' => 'source_name',
            'label' => trans('admin::app.prospects.index.datagrid.source'),
            'type' => 'string',
            'searchable' => true,
            'sortable' => true,
            'filterable' => false,
        ]);

        $this->addColumn([
            'index' => 'contacts_count',
            'label' => trans('admin::app.prospects.index.datagrid.contacts-count'),
            'type' => 'string',
            'searchable' => false,
            'sortable' => false,
            'filterable' => false,
            'closure' => fn ($row) => $this->prospectContactRepository->findWhere(['prospect_id' => $row->id])->count(),
        ]);

        $this->addColumn([
            'index' => 'created_at',
            'label' => trans('admin::app.prospects.index.datagrid.created-at'),
            'type' => 'date',
            'searchable' => true,
            'filterable' => true,
            'filterable_type' => 'date_range',
            'sortable' => true,
            'closure' => fn ($row) => core()->formatDate($row->created_at),
        ]);
    }

    /**
     * Prepare actions.
     */
    public function prepareActions(): void
    {
        if (bouncer()->hasPermission('prospects.edit')) {
            $this->addAction([
                'icon' => 'icon-edit',
                'title' => trans('admin::app.prospects.index.datagrid.edit'),
                'method' => 'GET',
                'url' => fn ($row) => route('admin.prospects.edit', $row->id),
            ]);
        }

        if (bouncer()->hasPermission('prospects.delete')) {
            $this->addAction([
                'icon' => 'icon-delete',
                'title' => trans('admin::app.prospects.index.datagrid.delete'),
                'method' => 'DELETE',
                'url' => fn ($row) => route('admin.prospects.delete', $row->id),
            ]);
        }
    }

    /**
     * Prepare mass actions.
     */
    public function prepareMassActions(): void
    {
        $this->addMassAction([
            'icon' => 'icon-delete',
            'title' => trans('admin::app.prospects.index.datagrid.delete'),
            'method' => 'PUT',
            'url' => route('admin.prospects.mass_delete'),
        ]);
    }
}
