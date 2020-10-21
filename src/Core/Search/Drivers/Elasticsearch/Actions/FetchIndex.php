<?php

namespace GetCandy\Api\Core\Search\Drivers\Elasticsearch\Actions;

use Elastica\Client;
use Lorisleiva\Actions\Action;
use GetCandy\Api\Core\Addresses\Models\Address;
use GetCandy\Api\Core\Search\Drivers\Elasticsearch\Index;

class FetchIndex extends Action
{
    /**
     * Determine if the user is authorized to make this action.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the action.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'languges' => 'array',
            'type' => 'required',
            'uuid' => 'required',
        ];
    }

    /**
     * Execute the action and return a result.
     *
     * @return \GetCandy\Api\Core\Addresses\Models\Address
     */
    public function handle(Client $client)
    {
        $indexes = [];
        $prefix = config('getcandy.search.index_prefix', 'getcandy');
        foreach ($this->languages as $language) {
            $index = $client->getIndex(
                "{$prefix}_{$this->type}_{$language}_{$this->uuid}"
            );

            if (!$index->exists()) {
                $index->create([
                    'settings' => [
                        'analysis' => [
                            'analyzer' => [
                                'trigram' => [
                                    'type' => 'custom',
                                    'tokenizer' => 'standard',
                                    'filter' => ['shingle'],
                                ],
                                'standard_lowercase' => [
                                    'type' => 'custom',
                                    'tokenizer' => 'standard',
                                    'filter' => ['lowercase'],
                                ],
                                'candy' => [
                                    'tokenizer' => 'standard',
                                    'filter' => ['lowercase', 'stop', 'porter_stem'],
                                ],
                            ],
                            'filter' => [
                                'shingle' => [
                                    'type' => 'shingle',
                                    'min_shingle_size' => 2,
                                    'max_shingle_size' => 3,
                                ],
                            ],
                        ],
                    ],
                ]);
            }

            $indexes[] = new Index($index, $language);
        }

        return $indexes;
    }
}