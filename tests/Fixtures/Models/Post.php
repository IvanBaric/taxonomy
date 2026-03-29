<?php

declare(strict_types=1);

namespace IvanBaric\Taxonomy\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use IvanBaric\Taxonomy\Traits\HasTaxonomies;

class Post extends Model
{
    use HasTaxonomies;

    protected $table = 'posts';

    protected $fillable = [
        'title',
    ];
}
