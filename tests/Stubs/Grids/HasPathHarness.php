<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Grids;

use \Illuminate\Database\Eloquent\Model;
use Modules\Core\Grids\Definitions\HasPath;
use ReflectionMethod;

final class HasPathHarness
{
    use HasPath;

    public function __construct(string $path, string $name)
    {
        $this->path = $path;
        $this->name = $name;
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function split(string $fullpath): array
    {
        $method = new ReflectionMethod(self::class, 'splitPath');
        $method->setAccessible(true);

        /** @var array{0:string,1:string} $result */
        return $method->invoke(null, $fullpath);
    }
}
