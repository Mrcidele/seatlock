<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

/**
 * @return array<string, array{string, string}> arquivo => [caminho, classe]
 */
function appClasses(string $subdir = ''): array
{
    $classes = [];
    $base = app_path($subdir);

    foreach (Finder::create()->files()->name('*.php')->in($base) as $file) {
        $relative = Str::of($file->getRealPath())->after(app_path().DIRECTORY_SEPARATOR)->beforeLast('.php')->replace('/', '\\');
        $classes[(string) $relative] = [$file->getRealPath(), 'App\\'.$relative];
    }

    return $classes;
}

it('declares strict types in every file under app/', function (): void {
    foreach (appClasses() as [$path]) {
        expect(file_get_contents($path))->toContain('declare(strict_types=1);');
    }
});

it('has no debugging leftovers', function (): void {
    foreach (appClasses() as [$path]) {
        expect(file_get_contents($path))->not->toMatch('/\b(dd|dump|var_dump|ray)\s*\(/');
    }
});

it('keeps value objects final and readonly', function (): void {
    foreach (appClasses('ValueObjects') as [, $class]) {
        $reflection = new ReflectionClass($class);
        expect($reflection->isFinal())->toBeTrue($class)->and($reflection->isReadOnly())->toBeTrue($class);
    }
});

// Octane mantém o processo vivo entre requests: as ações de domínio não
// podem guardar estado mutável.
it('keeps booking actions stateless (final readonly)', function (): void {
    foreach (appClasses('Booking/Actions') as [, $class]) {
        $reflection = new ReflectionClass($class);
        expect($reflection->isFinal())->toBeTrue($class)
            ->and($reflection->isReadOnly())->toBeTrue($class)
            ->and($reflection->getProperties(ReflectionProperty::IS_STATIC))->toBe([]);
    }
});

it('only has backed enums in App\Enums', function (): void {
    foreach (appClasses('Enums') as [, $class]) {
        expect(enum_exists($class))->toBeTrue($class)
            ->and((new ReflectionEnum($class))->isBacked())->toBeTrue($class);
    }
});

it('keeps the domain independent from HTTP controllers', function (): void {
    foreach ([...appClasses('Booking'), ...appClasses('SeatLock'), ...appClasses('Payments/Actions')] as [$path, $class]) {
        expect(str_contains((string) file_get_contents($path), 'App\\Http\\Controllers'))->toBeFalse($class);
    }
});
