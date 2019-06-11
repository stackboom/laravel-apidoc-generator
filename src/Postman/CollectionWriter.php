<?php

namespace Mpociot\ApiDoc\Postman;

use Ramsey\Uuid\Uuid;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

class CollectionWriter
{
    /**
     * @var Collection
     */
    private $routeGroups;

    /**
     * CollectionWriter constructor.
     *
     * @param Collection $routeGroups
     */
    public function __construct(Collection $routeGroups)
    {
        $this->routeGroups = $routeGroups;
    }

    public function getCollection()
    {
        URL::forceRootUrl(config('app.url'));

        $collection = [
            'variables' => [],
            'info' => [
                'name' => config('apidoc.postman.name') ?: config('app.name').' API',
                '_postman_id' => Uuid::uuid4()->toString(),
                'description' => config('apidoc.postman.description') ?: '',
                'schema' => 'https://schema.getpostman.com/json/collection/v2.0.0/collection.json',
            ],
            'item' => $this->routeGroups->map(function ($routes, $groupName) {
                return [
                    'name' => $groupName,
                    'description' => '',
                    'item' => $routes->map(function ($route) {
                        $mode = $route['methods'][0] === 'PUT' ? 'urlencoded' : 'formdata';
                        $parsed_uri = parse_url(url($route['uri']));
                        return [
                            'name' => $route['title'] != '' ? $route['title'] : url($route['uri']),
                            'request' => [
                                'url' => [
                                    'raw'=>url($route['uri']),
                                    'protocol'=> $parsed_uri['schema']??'http',
                                    "host"=> [
                                        $parsed_uri['host']??'localhost'
                                    ],
                                    "port"=>$parsed_uri['port']??'80',
                                    "query"=>collect($route['queryParameters'])->map(function($parameter, $key){
                                        return [
                                            'key' => $key,
                                            'value' => isset($parameter['value']) ? $parameter['value'] : '',
                                            'required' => isset($parameter['required']) ? $parameter['required'] : false,
                                            'enabled' => true,
                                        ];
                                    })->values(),
                                    'path'=>explode('/',$route['uri'])
                                ],
                                'method' => $route['methods'][0],
                                'body' => [
                                    'mode' => $mode,
                                    $mode => collect($route['bodyParameters'])->map(function ($parameter, $key) {
                                        return [
                                            'key' => $key,
                                            'value' => isset($parameter['value']) ? $parameter['value'] : '',
                                            'type' => 'text',
                                            'enabled' => true,
                                        ];
                                    })->values()->toArray(),
                                ],
                                'description' => $route['description'],
                                'response' => [],
                            ],
                        ];
                    })->toArray(),
                ];
            })->values()->toArray(),
        ];

        return json_encode($collection);
    }
}
