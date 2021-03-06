<?php

namespace markhuot\CraftQL\Types;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\Type;

class EntryEdge extends ObjectType {

    static function type($request) {
        return new static([
            'name' => 'EntryEdge',

            // this has to be a callback because `EntryEdge` returns an `EntryConnection`
            // which references our own `EntryEdge` that creates an immediate circular
            // reference. The `fields` callback allows `graphql-php` to work around the
            // circular reference.
            'fields' => function () use ($request) {
                $fields = [];
                $fields['cursor'] = Type::string();
                $fields['node'] = ['type' => \markhuot\CraftQL\Types\Entry::interface($request), 'resolve' => function ($root, $args, $context, $info) {
                    return $root['node'];
                }];
                $fields['relatedTo'] = [
                    'type' => \markhuot\CraftQL\Types\EntryConnection::type($request),
                    'args' => array_merge(\markhuot\CraftQL\Types\Entry::args($request), [
                        'source' => Type::boolean(),
                        'target' => Type::boolean(),
                        'field' => Type::string(),
                        'sourceLocale' => Type::string(),
                    ]),
                    'resolve' => function ($root, $args, $context, $info) use ($request) {
                        $criteria = \craft\elements\Entry::find();
                        $criteria = $criteria->relatedTo([
                            'element' => !@$args['source'] && !@$args['target'] ? $root['node']->id : null,
                            'sourceElement' => @$args['source'] == true ? $root['node']->id : null,
                            'targetElement' => @$args['target'] == true ? $root['node']->id : null,
                            'field' => @$args['field'] ?: null,
                            'sourceLocale' => @$args['sourceLocale'] ?: null,
                        ]);
                        unset($args['source']);
                        unset($args['target']);
                        unset($args['field']);
                        unset($args['sourceLocale']);
                        $criteria = $request->entries($criteria, $args, $info);
                        list($pageInfo, $entries) = \craft\helpers\Template::paginateCriteria($criteria);

                        return [
                            'totalCount' => $pageInfo->total,
                            'pageInfo' => $pageInfo,
                            'edges' => $entries,
                        ];
                    },
                ];

                // @optional could expose each entry type next to the generic node
                // foreach ($request->entryTypes()->all() as $entryType) {
                //     $fields[$entryType->config['craftType']->handle] = ['type' => $entryType, 'resolve' => function ($root, $args) {
                //         return $root['node'];
                //     }];
                // }

                return $fields;
            },
        ]);
    }

}