<?php
/**
 * Created by PhpStorm.
 * User: eduardocruz
 * Date: 11/9/15
 * Time: 11:47
 */

namespace app\Ninja\Import\FreshBooks;

use League\Fractal\TransformerAbstract


class ClientTransformer extends TransformerAbstract
{
    public function transform(Book $book)
    {
        return [
            'id'      => (int) $book->id,
            'title'   => $book->title,
            'year'    => (int) $book->yr,
            'links'   => [
                [
                    'rel' => 'self',
                    'uri' => '/books/'.$book->id,
                ]
            ],
        ];
    }

}