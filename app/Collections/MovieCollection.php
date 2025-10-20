<?php

declare(strict_types=1);

namespace App\Collections;

use App\Models\Movie;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends Collection<int, Movie>
 */
class MovieCollection extends Collection
{
    /**
     * @return $this
     */
    public function withListRelations(): self
    {
        if ($this->isEmpty()) {
            return $this;
        }

        $relations = Movie::listRelationConstraints();

        if ($relations === []) {
            return $this;
        }

        $this->load($relations);

        return $this;
    }
}
