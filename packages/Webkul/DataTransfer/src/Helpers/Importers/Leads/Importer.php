<?php

namespace Webkul\DataTransfer\Helpers\Importers\Leads;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Webkul\Attribute\Models\AttributeValue;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Attribute\Repositories\AttributeValueRepository;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Core\Contracts\Validations\Decimal;
use Webkul\DataTransfer\Contracts\ImportBatch as ImportBatchContract;
use Webkul\DataTransfer\Helpers\Import;
use Webkul\DataTransfer\Helpers\Importers\AbstractImporter;
use Webkul\DataTransfer\Repositories\ImportBatchRepository;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Lead\Repositories\PipelineRepository;
use Webkul\Lead\Repositories\ProductRepository as LeadProductRepository;
use Webkul\Lead\Repositories\SourceRepository;
use Webkul\Lead\Repositories\TypeRepository;
use Webkul\Product\Repositories\ProductRepository as CatalogProductRepository;
use Webkul\User\Repositories\UserRepository;

class Importer extends AbstractImporter
{
    /**
     * Error code for non existing id.
     */
    const ERROR_ID_NOT_FOUND_FOR_DELETE = 'id_not_found_to_delete';

    /**
     * Error code for a row that identifies no person at all.
     */
    const ERROR_PERSON_IDENTIFICATION_MISSING = 'person_identification_missing';

    /**
     * Permanent entity columns.
     *
     * In addition to the legacy, raw-id columns (kept for re-importing exported data), this
     * accepts plain-text columns so a lead can be bulk-uploaded without knowing any internal
     * database ids: `sales_owner` (email), `person_name`/`person_email`/`person_phone` (creates
     * or matches a contact), `organization_name`, `lead_source`/`lead_type` (matched by name,
     * auto-created if new), `pipeline`/`stage` (matched by name), and `product_name`/
     * `product_price`/`product_quantity` (replacing the packed `product` string).
     */
    protected array $validColumnNames = [
        'id',
        'title',
        'description',
        'lead_value',
        'status',
        'lost_reason',
        'closed_at',
        'user_id',
        'sales_owner',
        'person_id',
        'person_name',
        'person_email',
        'person_phone',
        'organization_name',
        'lead_source_id',
        'lead_source',
        'lead_type_id',
        'lead_type',
        'lead_pipeline_id',
        'pipeline',
        'lead_pipeline_stage_id',
        'stage',
        'expected_close_date',
        'product',
        'product_name',
        'product_price',
        'product_quantity',
    ];

    /**
     * Error message templates.
     */
    protected array $messages = [
        self::ERROR_ID_NOT_FOUND_FOR_DELETE => 'admin::app.settings.data-transfer.importers.leads.validation.errors.id-not-found',
        self::ERROR_PERSON_IDENTIFICATION_MISSING => 'admin::app.settings.data-transfer.importers.leads.validation.errors.person-identification-missing',
    ];

    /**
     * Cache of resolved lead source ids, keyed by lowercased name.
     */
    protected array $sourceCache = [];

    /**
     * Cache of resolved lead type ids, keyed by lowercased name.
     */
    protected array $typeCache = [];

    /**
     * Cache of resolved sales owner (user) ids, keyed by lowercased email.
     */
    protected array $userEmailCache = [];

    /**
     * Cache of resolved person ids, keyed by lowercased email.
     */
    protected array $personEmailCache = [];

    /**
     * Cache of resolved person ids, keyed by phone number.
     */
    protected array $personPhoneCache = [];

    /**
     * The user id attributed to leads/persons when no sales owner can be resolved (e.g. a queued
     * import job has no authenticated request user). Resolved once and cached.
     */
    protected ?int $fallbackUserId = null;

    /**
     * The default pipeline (with its stages), cached since a blank `pipeline`/`stage` column —
     * the common case — resolves to it on every row.
     */
    protected $defaultPipelineCache;

    /**
     * Permanent entity columns.
     *
     * @var string[]
     */
    protected $permanentAttributes = ['title'];

    /**
     * Permanent entity column.
     */
    protected string $masterAttributeCode = 'id';

    /**
     * Is linking required
     */
    protected bool $linkingRequired = true;

    /**
     * Create a new helper instance.
     *
     * @return void
     */
    public function __construct(
        protected ImportBatchRepository $importBatchRepository,
        protected LeadRepository $leadRepository,
        protected LeadProductRepository $leadProductRepository,
        protected AttributeRepository $attributeRepository,
        protected AttributeValueRepository $attributeValueRepository,
        protected Storage $leadsStorage,
        protected PersonRepository $personRepository,
        protected UserRepository $userRepository,
        protected SourceRepository $sourceRepository,
        protected TypeRepository $typeRepository,
        protected PipelineRepository $pipelineRepository,
        protected CatalogProductRepository $catalogProductRepository,
    ) {
        parent::__construct(
            $importBatchRepository,
            $attributeRepository,
            $attributeValueRepository,
        );

        $this->initAttributes();
    }

    /**
     * Append the lead attribute codes (including user defined ones) to the list of valid columns
     * so that custom attributes can be imported alongside the built-in fields.
     */
    protected function initAttributes(): void
    {
        $attributes = $this->attributeRepository->findWhere(['entity_type' => 'leads']);

        foreach ($attributes as $attribute) {
            $this->validColumnNames[] = $attribute->code;
        }
    }

    /**
     * Initialize leads error templates.
     */
    protected function initErrorMessages(): void
    {
        foreach ($this->messages as $errorCode => $message) {
            $this->errorHelper->addErrorMessage($errorCode, trans($message));
        }

        parent::initErrorMessages();
    }

    /**
     * Validate data.
     */
    public function validateData(): void
    {
        $this->leadsStorage->init();

        parent::validateData();
    }

    /**
     * Validates row.
     */
    public function validateRow(array $rowData, int $rowNumber): bool
    {
        /**
         * If row is already validated than no need for further validation.
         */
        if (isset($this->validatedRows[$rowNumber])) {
            return ! $this->errorHelper->isRowInvalid($rowNumber);
        }

        $this->validatedRows[$rowNumber] = true;

        /**
         * If import action is delete than no need for further validation.
         */
        if ($this->import->action == Import::ACTION_DELETE) {
            if (! $this->isTitleExist($rowData['title'])) {
                $this->skipRow($rowNumber, self::ERROR_ID_NOT_FOUND_FOR_DELETE, 'id');

                return false;
            }

            return true;
        }

        /**
         * The legacy raw-id columns are only checked when actually supplied (re-importing an
         * export). The friendly `person_name`/`sales_owner`/`lead_source`/`lead_type`/`pipeline`/
         * `stage`/`product_name` columns are resolved (and, where approved, auto-created) later in
         * saveLeads()/linkBatch() rather than here, so a brand-new name never fails validation.
         */
        $validator = Validator::make($rowData, [
            ...$this->getValidationRules('leads|persons', $rowData),
            'id' => 'numeric',
            'status' => 'sometimes|required|in:0,1',
            'user_id' => 'nullable|exists:users,id',
            'person_id' => 'nullable|exists:persons,id',
            'lead_source_id' => 'nullable|exists:lead_sources,id',
            'lead_type_id' => 'nullable|exists:lead_types,id',
            'lead_pipeline_id' => 'nullable|exists:lead_pipelines,id',
            'lead_pipeline_stage_id' => 'nullable|exists:lead_pipeline_stages,id',
        ]);

        if ($validator->fails()) {
            $failedAttributes = $validator->failed();

            foreach ($validator->errors()->getMessages() as $attributeCode => $message) {
                $errorCode = array_key_first($failedAttributes[$attributeCode] ?? []);

                $this->skipRow($rowNumber, $errorCode, $attributeCode, current($message));
            }
        }

        /**
         * A lead always needs a contact: either an existing person (by id) or enough to create one
         * (a name). Product, source, type, pipeline, stage and sales owner all have safe defaults,
         * so they never need to block a row.
         */
        if (
            empty($rowData['person_id'])
            && empty($rowData['person_name'])
        ) {
            $this->skipRow($rowNumber, self::ERROR_PERSON_IDENTIFICATION_MISSING, 'person_name');
        }

        return ! $this->errorHelper->isRowInvalid($rowNumber);
    }

    /**
     * Prepare row data for lead product.
     */
    protected function parseProducts(?string $products): array
    {
        $productData = [];

        $productArray = explode(',', $products);

        foreach ($productArray as $product) {
            if (empty($product)) {
                continue;
            }

            [$key, $value] = explode('=', $product);

            $productData[$key] = $value;
        }

        if (
            isset($productData['price'])
            && isset($productData['quantity'])
        ) {
            $productData['amount'] = $productData['price'] * $productData['quantity'];
        }

        return $productData;
    }

    /**
     * Get validation rules.
     */
    public function getValidationRules(string $entityTypes, array $rowData): array
    {
        $rules = [];

        foreach (explode('|', $entityTypes) as $entityType) {
            $attributes = $this->attributeRepository->scopeQuery(fn ($query) => $query->whereIn('code', array_keys($rowData))->where('entity_type', $entityType))->get();

            foreach ($attributes as $attribute) {
                if ($entityType == 'persons') {
                    $attribute->code = 'person.'.$attribute->code;
                }

                $validations = [];

                if ($attribute->type == 'boolean') {
                    continue;
                } elseif ($attribute->type == 'address') {
                    if (! $attribute->is_required) {
                        continue;
                    }

                    $validations = [
                        $attribute->code.'.address' => 'required',
                        $attribute->code.'.country' => 'required',
                        $attribute->code.'.state' => 'required',
                        $attribute->code.'.city' => 'required',
                        $attribute->code.'.postcode' => 'required',
                    ];
                } elseif ($attribute->type == 'email') {
                    $validations = [
                        $attribute->code => [$attribute->is_required ? 'required' : 'nullable'],
                        $attribute->code.'.*.value' => [$attribute->is_required ? 'required' : 'nullable', 'email'],
                        $attribute->code.'.*.label' => $attribute->is_required ? 'required' : 'nullable',
                    ];
                } elseif ($attribute->type == 'phone') {
                    $validations = [
                        $attribute->code => [$attribute->is_required ? 'required' : 'nullable'],
                        $attribute->code.'.*.value' => [$attribute->is_required ? 'required' : 'nullable'],
                        $attribute->code.'.*.label' => $attribute->is_required ? 'required' : 'nullable',
                    ];
                } else {
                    $validations[$attribute->code] = [$attribute->is_required ? 'required' : 'nullable'];

                    if ($attribute->type == 'text' && $attribute->validation) {
                        array_push($validations[$attribute->code],
                            $attribute->validation == 'decimal'
                            ? new Decimal
                            : $attribute->validation
                        );
                    }

                    if ($attribute->type == 'price') {
                        array_push($validations[$attribute->code], new Decimal);
                    }
                }

                if ($attribute->is_unique) {
                    array_push($validations[in_array($attribute->type, ['email', 'phone'])
                        ? $attribute->code.'.*.value'
                        : $attribute->code
                    ], function ($field, $value, $fail) use ($attribute) {
                        if (! $this->attributeValueRepository->isValueUnique(
                            null,
                            $attribute->entity_type,
                            $attribute,
                            request($field)
                        )
                        ) {
                            $fail(trans('admin::app.settings.data-transfer.validation.errors.already-exists', ['attribute' => $attribute->name]));
                        }
                    });
                }

                $rules = [
                    ...$rules,
                    ...$validations,
                ];
            }
        }

        return $rules;
    }

    /**
     * Start the import process.
     */
    public function importBatch(ImportBatchContract $batch): bool
    {
        Event::dispatch('data_transfer.imports.batch.import.before', $batch);

        if ($batch->import->action == Import::ACTION_DELETE) {
            $this->deleteLeads($batch);
        } else {
            $this->saveLeads($batch);
        }

        /**
         * Update import batch summary.
         */
        $batch = $this->importBatchRepository->update([
            'state' => Import::STATE_PROCESSED,

            'summary' => [
                'created' => $this->getCreatedItemsCount(),
                'updated' => $this->getUpdatedItemsCount(),
                'deleted' => $this->getDeletedItemsCount(),
            ],
        ], $batch->id);

        Event::dispatch('data_transfer.imports.batch.import.after', $batch);

        return true;
    }

    /**
     * Start the products linking process
     */
    public function linkBatch(ImportBatchContract $batch): bool
    {
        Event::dispatch('data_transfer.imports.batch.linking.before', $batch);

        /**
         * Load leads storage with batch ids.
         */
        $this->leadsStorage->load(Arr::pluck($batch->data, 'title'));

        $products = [];

        foreach ($batch->data as $rowData) {
            /**
             * Prepare products.
             */
            $this->prepareProducts($rowData, $products);
        }

        $this->saveProducts($products);

        /**
         * Update import batch summary
         */
        $this->importBatchRepository->update([
            'state' => Import::STATE_LINKED,
        ], $batch->id);

        Event::dispatch('data_transfer.imports.batch.linking.after', $batch);

        return true;
    }

    /**
     * Prepare products.
     */
    public function prepareProducts($rowData, &$product): void
    {
        $productData = $this->resolveProductData($rowData);

        if ($productData) {
            $product[$rowData['title']] = $productData;
        }
    }

    /**
     * Resolve the product to attach to a lead, from either the legacy packed `product` column or
     * the plain `product_name`/`product_price`/`product_quantity` columns. Product is entirely
     * optional: a missing or unmatched product never blocks the lead itself from being imported —
     * it's simply not attached.
     */
    protected function resolveProductData(array $rowData): ?array
    {
        if (! empty($rowData['product'])) {
            $productData = $this->parseProducts($rowData['product']);

            if (
                empty($productData['id'])
                || ! isset($productData['price'])
                || ! isset($productData['quantity'])
                || ! $this->catalogProductRepository->find($productData['id'])
            ) {
                return null;
            }

            return $productData;
        }

        if (empty($rowData['product_name'])) {
            return null;
        }

        $catalogProduct = $this->catalogProductRepository->findOneWhere(['name' => $rowData['product_name']]);

        if (! $catalogProduct) {
            return null;
        }

        $price = $rowData['product_price'] ?? $catalogProduct->price;

        $quantity = $rowData['product_quantity'] ?? 1;

        return [
            'id'       => $catalogProduct->id,
            'price'    => $price,
            'quantity' => $quantity,
            'amount'   => $price * $quantity,
        ];
    }

    /**
     * Save products.
     */
    public function saveProducts(array $products): void
    {
        $leadProducts = [];

        foreach ($products as $title => $product) {
            $lead = $this->leadsStorage->get($title);

            $leadProducts['insert'][] = [
                'lead_id' => $lead['id'],
                'product_id' => $product['id'],
                'price' => $product['price'],
                'quantity' => $product['quantity'],
                'amount' => $product['amount'],
            ];
        }

        /**
         * Nothing to link when none of the rows in this batch reference a product.
         */
        if (empty($leadProducts['insert'])) {
            return;
        }

        foreach ($leadProducts['insert'] as $key => $leadProduct) {
            $this->leadProductRepository->deleteWhere([
                'lead_id' => $leadProduct['lead_id'],
                'product_id' => $leadProduct['product_id'],
            ]);
        }

        $this->leadProductRepository->upsert($leadProducts['insert'], ['lead_id', 'product_id']);
    }

    /**
     * Delete leads from current batch.
     */
    protected function deleteLeads(ImportBatchContract $batch): bool
    {
        /**
         * Load leads storage with batch ids.
         */
        $this->leadsStorage->load(Arr::pluck($batch->data, 'title'));

        $idsToDelete = [];

        foreach ($batch->data as $rowData) {
            if (! $this->isTitleExist($rowData['title'])) {
                continue;
            }

            $idsToDelete[] = $this->leadsStorage->get($rowData['title']);
        }

        $idsToDelete = array_unique($idsToDelete);

        $this->deletedItemsCount = count($idsToDelete);

        $this->leadRepository->deleteWhere([['id', 'IN', $idsToDelete]]);

        return true;
    }

    /**
     * Save leads from current batch.
     */
    protected function saveLeads(ImportBatchContract $batch): bool
    {
        /**
         * Load lead storage with batch unique title.
         */
        $this->leadsStorage->load(Arr::pluck($batch->data, 'title'));

        /**
         * Warm the person-by-email cache for the whole batch in a single query, mirroring
         * Persons\Importer::savePersonData()'s bulk lookup, so resolvePersonId() below doesn't
         * issue one query per row for contacts that already exist.
         */
        $this->preloadPersonCache($batch->data);

        $existingLeadsById = $this->loadExistingLeadsById($batch->data);

        $leads = [];

        $attributeValues = [];

        /**
         * Prepare leads for import.
         */
        foreach ($batch->data as $rowData) {
            $rowData = $this->resolveRowReferences(
                $rowData,
                $existingLeadsById[$rowData['id'] ?? null] ?? null
            );

            /**
             * Custom (user defined) attribute values are not columns on the leads table; they are
             * persisted separately via saveAttributeValues(). Only the native columns are written
             * to the leads table here.
             */
            $native = Arr::only($rowData, $this->getEntityColumns());

            if (isset($rowData['id'])) {
                $leads['update'][$rowData['id']] = $native;
            } else {
                $leads['insert'][$rowData['title']] = [
                    ...Arr::except($native, ['id']),
                    'created_at' => $rowData['created_at'] ?? now(),
                    'updated_at' => $rowData['updated_at'] ?? now(),
                ];
            }

            $this->prepareAttributeValues($rowData, $attributeValues);
        }

        if (! empty($leads['update'])) {
            $this->updatedItemsCount += count($leads['update']);

            $this->leadRepository->upsert(
                $leads['update'],
                $this->masterAttributeCode
            );
        }

        if (! empty($leads['insert'])) {
            $this->createdItemsCount += count($leads['insert']);

            $this->leadRepository->insert($leads['insert']);

            /**
             * Update the sku storage with newly created products
             */
            $newLeads = $this->leadRepository->findWhereIn(
                'title',
                array_keys($leads['insert']),
                [
                    'id',
                    'title',
                ]
            );

            foreach ($newLeads as $lead) {
                $this->leadsStorage->set($lead->title, [
                    'id' => $lead->id,
                    'title' => $lead->title,
                ]);
            }
        }

        $this->saveAttributeValues($attributeValues);

        return true;
    }

    /**
     * Load the current `user_id`/`person_id`/`lead_source_id`/`lead_type_id`/`lead_pipeline_id`/
     * `lead_pipeline_stage_id` of every lead this batch is updating, keyed by id. When a friendly
     * column (e.g. `sales_owner`) is left blank on an update row, we fall back to the lead's
     * existing value instead of inventing a new default, so a partial re-import never clobbers a
     * field the uploader didn't mean to touch.
     */
    protected function loadExistingLeadsById(array $rows): array
    {
        $ids = collect($rows)->pluck('id')->filter()->unique()->values()->all();

        if (empty($ids)) {
            return [];
        }

        return $this->leadRepository
            ->findWhereIn('id', $ids, [
                'id',
                'user_id',
                'person_id',
                'lead_source_id',
                'lead_type_id',
                'lead_pipeline_id',
                'lead_pipeline_stage_id',
            ])
            ->keyBy('id')
            ->all();
    }

    /**
     * Resolve every friendly (name/email based) column on the row into the raw id its legacy
     * counterpart expects, so the rest of saveLeads() can keep operating on plain leads-table
     * columns exactly as before. Legacy numeric columns, when supplied, always take precedence.
     */
    protected function resolveRowReferences(array $rowData, $existingLead = null): array
    {
        if (empty($rowData['user_id'])) {
            $rowData['user_id'] = $existingLead?->user_id
                ?? $this->resolveSalesOwnerId($rowData['sales_owner'] ?? null);
        }

        if (empty($rowData['person_id'])) {
            $rowData['person_id'] = $existingLead?->person_id
                ?? $this->resolvePersonId($rowData);
        }

        if (empty($rowData['lead_source_id'])) {
            $rowData['lead_source_id'] = $existingLead?->lead_source_id
                ?? $this->resolveSourceId($rowData['lead_source'] ?? null);
        }

        if (empty($rowData['lead_type_id'])) {
            $rowData['lead_type_id'] = $existingLead?->lead_type_id
                ?? $this->resolveTypeId($rowData['lead_type'] ?? null);
        }

        if (
            empty($rowData['lead_pipeline_id'])
            || empty($rowData['lead_pipeline_stage_id'])
        ) {
            [$pipelineId, $stageId] = $this->resolvePipelineAndStage(
                $rowData['lead_pipeline_id'] ?? null,
                $rowData['pipeline'] ?? null,
                $rowData['lead_pipeline_stage_id'] ?? null,
                $rowData['stage'] ?? null
            );

            $rowData['lead_pipeline_id'] = $rowData['lead_pipeline_id'] ?: ($existingLead?->lead_pipeline_id ?? $pipelineId);
            $rowData['lead_pipeline_stage_id'] = $rowData['lead_pipeline_stage_id'] ?: ($existingLead?->lead_pipeline_stage_id ?? $stageId);
        }

        return $rowData;
    }

    /**
     * Find or create a lead source by name (case-sensitive exact match, mirroring
     * `PersonRepository::fetchOrCreateOrganizationByName()`). Blank names fall back to a shared
     * "General" source, since `lead_source_id` is `NOT NULL` on the leads table.
     */
    protected function resolveSourceId(?string $name): int
    {
        $name = trim((string) $name) ?: 'General';

        $key = strtolower($name);

        if (isset($this->sourceCache[$key])) {
            return $this->sourceCache[$key];
        }

        $source = $this->sourceRepository->findOneWhere(['name' => $name])
            ?: $this->sourceRepository->create(['name' => $name]);

        return $this->sourceCache[$key] = $source->id;
    }

    /**
     * Find or create a lead type by name. See resolveSourceId() for the rationale.
     */
    protected function resolveTypeId(?string $name): int
    {
        $name = trim((string) $name) ?: 'General';

        $key = strtolower($name);

        if (isset($this->typeCache[$key])) {
            return $this->typeCache[$key];
        }

        $type = $this->typeRepository->findOneWhere(['name' => $name])
            ?: $this->typeRepository->create(['name' => $name]);

        return $this->typeCache[$key] = $type->id;
    }

    /**
     * Resolve the sales owner by email (case-insensitive). Pipelines/stages are structural rather
     * than simple tags, so — unlike source/type — nothing is auto-created here: a blank or
     * unmatched email just falls back to whoever is running the import.
     */
    protected function resolveSalesOwnerId(?string $email): int
    {
        $email = trim((string) $email);

        if ($email !== '') {
            $key = strtolower($email);

            if (isset($this->userEmailCache[$key])) {
                return $this->userEmailCache[$key];
            }

            $user = $this->userRepository->findOneWhere(['email' => $email]);

            if ($user) {
                return $this->userEmailCache[$key] = $user->id;
            }
        }

        return $this->getFallbackUserId();
    }

    /**
     * The user id attributed to leads/persons when no sales owner can be resolved. Prefers the
     * authenticated admin running the import; queued workers have no request/auth context, so this
     * falls back further to the first user in the system rather than risk a null insert into the
     * leads table's `NOT NULL` `user_id` column.
     */
    protected function getFallbackUserId(): int
    {
        if ($this->fallbackUserId !== null) {
            return $this->fallbackUserId;
        }

        $id = auth()->guard('user')->id() ?: $this->userRepository->first()?->id;

        return $this->fallbackUserId = $id;
    }

    /**
     * Bulk-load persons matching every distinct `person_email` in the batch, so resolvePersonId()
     * can check the cache instead of issuing a query per row.
     */
    protected function preloadPersonCache(array $rows): void
    {
        $emails = collect($rows)->pluck('person_email')->filter()->unique()->values();

        if ($emails->isEmpty()) {
            return;
        }

        $persons = $this->personRepository->where(function ($query) use ($emails) {
            foreach ($emails as $email) {
                $query->orWhereJsonContains('emails', [['value' => $email]]);
            }
        })->get();

        foreach ($persons as $person) {
            foreach ($person->emails ?? [] as $emailEntry) {
                $this->personEmailCache[strtolower($emailEntry['value'])] = $person->id;
            }
        }
    }

    /**
     * Find or create the person a lead row identifies. Matches an existing contact by email first
     * (via the cache warmed in preloadPersonCache()), then by phone number, and otherwise creates a
     * brand-new Person — via `PersonRepository::create()` so `organization_name` is resolved into
     * an organization the same way it already is for contacts created from the UI.
     */
    protected function resolvePersonId(array $rowData): int
    {
        $email = trim((string) ($rowData['person_email'] ?? ''));

        $phone = trim((string) ($rowData['person_phone'] ?? ''));

        $name = trim((string) ($rowData['person_name'] ?? ''));

        if (
            $email !== ''
            && isset($this->personEmailCache[strtolower($email)])
        ) {
            return $this->personEmailCache[strtolower($email)];
        }

        if (
            $phone !== ''
            && isset($this->personPhoneCache[$phone])
        ) {
            return $this->personPhoneCache[$phone];
        }

        $person = $this->personRepository->create([
            'entity_type' => 'persons',
            'name' => $name !== '' ? $name : ($email !== '' ? $email : $phone),
            'emails' => $email !== '' ? [['value' => $email, 'label' => 'work']] : [],
            'contact_numbers' => $phone !== '' ? [['value' => $phone, 'label' => 'work']] : [],
            'organization_name' => $rowData['organization_name'] ?? null,
            'user_id' => $rowData['user_id'] ?? null,
        ]);

        if ($email !== '') {
            $this->personEmailCache[strtolower($email)] = $person->id;
        }

        if ($phone !== '') {
            $this->personPhoneCache[$phone] = $person->id;
        }

        return $person->id;
    }

    /**
     * Resolve the pipeline and stage a lead belongs to. Unlike source/type, pipelines and stages
     * are structural rather than simple tags, so neither is auto-created: a blank or unmatched name
     * falls back to the default pipeline and its first stage (the same fallback
     * `LeadController::store()` already uses for the quick-add flow).
     *
     * @return array{0: int, 1: ?int}
     */
    protected function resolvePipelineAndStage(
        null|int|string $pipelineId,
        ?string $pipelineName,
        null|int|string $stageId,
        ?string $stageName
    ): array {
        $pipeline = null;

        if (! empty($pipelineId)) {
            $pipeline = $this->pipelineRepository->find($pipelineId);
        } elseif (! empty($pipelineName)) {
            $pipeline = $this->pipelineRepository->findOneWhere(['name' => trim($pipelineName)]);
        }

        $pipeline = $pipeline ?: ($this->defaultPipelineCache ??= $this->pipelineRepository->getDefaultPipeline());

        $stage = null;

        if (! empty($stageId)) {
            $stage = $pipeline->stages->firstWhere('id', (int) $stageId);
        } elseif (! empty($stageName)) {
            $stageName = trim($stageName);

            $stage = $pipeline->stages->first(
                fn ($candidate) => strcasecmp($candidate->name, $stageName) === 0
            );
        }

        $stage = $stage ?: $pipeline->stages->first();

        return [$pipeline->id, $stage?->id];
    }

    /**
     * The native columns of the leads table (custom attribute values are stored separately).
     */
    protected function getEntityColumns(): array
    {
        static $columns;

        return $columns ??= Schema::getColumnListing('leads');
    }

    /**
     * Map each row's lead attribute values (including user defined ones), keyed by lead title.
     */
    public function prepareAttributeValues(array $rowData, array &$attributeValues): void
    {
        foreach ($rowData as $code => $value) {
            if (is_null($value)) {
                continue;
            }

            $attribute = $this->attributeRepository->findOneWhere([
                'code' => $code,
                'entity_type' => 'leads',
            ]);

            if (! $attribute) {
                continue;
            }

            $attributeTypeValues = array_fill_keys(array_values(AttributeValue::$attributeTypeFields), null);

            $attributeValues[$rowData['title']][] = array_merge($attributeTypeValues, [
                'attribute_id' => $attribute->id,
                AttributeValue::$attributeTypeFields[$attribute->type] => $value,
            ]);
        }
    }

    /**
     * Upsert the collected lead attribute values into the EAV attribute_values table.
     */
    public function saveAttributeValues(array $attributeValues): void
    {
        $leadAttributeValues = [];

        foreach ($attributeValues as $title => $values) {
            $lead = $this->leadsStorage->get($title);

            if (! $lead) {
                continue;
            }

            foreach ($values as $attribute) {
                $attribute['entity_id'] = (int) $lead['id'];

                $attribute['unique_id'] = implode('|', array_filter([
                    $attribute['entity_id'],
                    $attribute['attribute_id'],
                ]));

                $attribute['entity_type'] = 'leads';

                $leadAttributeValues[$attribute['unique_id']] = $attribute;
            }
        }

        if (! empty($leadAttributeValues)) {
            $this->attributeValueRepository->upsert($leadAttributeValues, 'unique_id');
        }
    }

    /**
     * Check if title exists.
     */
    public function isTitleExist(string $title): bool
    {
        return $this->leadsStorage->has($title);
    }

    /**
     * Prepare row data to save into the database.
     */
    protected function prepareRowForDb(array $rowData): array
    {
        return parent::prepareRowForDb($rowData);
    }
}
