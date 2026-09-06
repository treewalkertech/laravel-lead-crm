<?php

namespace Webkul\Prospect\Repositories;

use Illuminate\Support\Arr;
use Webkul\Core\Eloquent\Repository;

class ProspectRepository extends Repository
{
    /**
     * Searchable fields.
     */
    protected $fieldSearchable = [
        'name',
        'industry',
        'user_id',
    ];

    /**
     * Specify model class name.
     *
     * @return mixed
     */
    public function model()
    {
        return 'Webkul\Prospect\Contracts\Prospect';
    }

    /**
     * Create a prospect together with its contacts.
     *
     * @return \Webkul\Prospect\Contracts\Prospect
     */
    public function create(array $data)
    {
        $prospect = parent::create($data);

        foreach ($data['contacts'] ?? [] as $contact) {
            if (empty($contact['name'])) {
                continue;
            }

            $prospect->contacts()->create(Arr::except($contact, ['converted_lead_id']));
        }

        return $prospect;
    }

    /**
     * Update a prospect together with its contacts.
     *
     * @param  int  $id
     * @return \Webkul\Prospect\Contracts\Prospect
     */
    public function update(array $data, $id)
    {
        $prospect = parent::update($data, $id);

        foreach ($data['contacts'] ?? [] as $contactId => $contact) {
            if (empty($contact['name'])) {
                continue;
            }

            $contact = Arr::except($contact, ['converted_lead_id']);

            if (is_numeric($contactId)) {
                $prospect->contacts()->whereKey($contactId)->update($contact);
            } else {
                $prospect->contacts()->create($contact);
            }
        }

        return $prospect;
    }
}
