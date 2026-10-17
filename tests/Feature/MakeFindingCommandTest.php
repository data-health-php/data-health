<?php

declare(strict_types=1);

use App\DataHealth\GeneratedBasicFinding;
use App\DataHealth\GeneratedCompleteFinding;
use DataHealth\Attributes\Async;
use DataHealth\Attributes\AutoResolve;
use DataHealth\Attributes\AutoVerify;
use DataHealth\Attributes\Scheduled;
use DataHealth\Contracts\CanDetect;
use DataHealth\Contracts\CanResolve;
use DataHealth\Contracts\CanVerify;
use DataHealth\Enums\FindingUrgency;
use DataHealth\Finding;
use DataHealth\FindingRegistry;
use DataHealth\Tests\Fixtures\Models\TestModel;
use Illuminate\Filesystem\Filesystem;

beforeEach(function () {
    $this->findingCommandDirectory = sys_get_temp_dir().'/data-health-make-finding-'.bin2hex(random_bytes(8));
    app()->setBasePath($this->findingCommandDirectory);
});

afterEach(function () {
    (new Filesystem)->deleteDirectory($this->findingCommandDirectory);
});

it('generates a detectable finding in the default directory', function () {
    $this->artisan('health:make:finding', ['name' => 'GeneratedBasicFinding'])
        ->expectsOutputToContain('created successfully')
        ->assertSuccessful();

    $path = base_path('app/DataHealth/GeneratedBasicFinding.php');
    require $path;

    expect(is_subclass_of(GeneratedBasicFinding::class, Finding::class))->toBeTrue()
        ->and(array_values(class_implements(GeneratedBasicFinding::class)))->toBe([CanDetect::class])
        ->and(GeneratedBasicFinding::detect())->toBeNull()
        ->and((new FindingRegistry)->getClass('GeneratedBasicFinding'))->toBe(GeneratedBasicFinding::class)
        ->and(file_get_contents($path))->toContain('declare(strict_types=1);');
});

it('generates the configured default interfaces and methods', function (array $interfaces) {
    config()->set('data-health.make_finding.interfaces', $interfaces);

    $name = 'ConfiguredFinding'.count($interfaces).implode('', array_map('class_basename', $interfaces));
    $this->artisan('health:make:finding', ['name' => $name])->assertSuccessful();

    require base_path('app/DataHealth/'.$name.'.php');
    $class = 'App\\DataHealth\\'.$name;

    expect(array_values(class_implements($class)))->toEqualCanonicalizing($interfaces);

    foreach ([CanDetect::class => 'detect', CanVerify::class => 'verify', CanResolve::class => 'resolve'] as $interface => $method) {
        expect(method_exists($class, $method))->toBe(in_array($interface, $interfaces, true));
    }
})->with([
    'no interfaces' => [[]],
    'verification only' => [[CanVerify::class]],
    'resolution only' => [[CanResolve::class]],
    'all interfaces' => [[CanDetect::class, CanVerify::class, CanResolve::class]],
]);

it('adds explicit capabilities to the configured defaults without duplicates', function () {
    config()->set('data-health.make_finding.interfaces', [CanResolve::class, CanVerify::class, CanResolve::class]);

    $this->artisan('health:make:finding', [
        'name' => 'CombinedDefaultsFinding',
        '--detect' => true,
        '--verify' => true,
    ])->assertSuccessful();

    require base_path('app/DataHealth/CombinedDefaultsFinding.php');

    expect(array_values(class_implements('App\\DataHealth\\CombinedDefaultsFinding')))
        ->toEqualCanonicalizing([CanDetect::class, CanVerify::class, CanResolve::class]);
});

it('generates nested findings with optional capabilities', function (string $option, string $contract, string $method, mixed $result) {
    config()->set('data-health.make_finding.interfaces', []);

    $name = 'Generated'.ucfirst($method).'Finding';
    $this->artisan('health:make:finding', ['name' => 'Customers/'.$name, '--'.$option => true])
        ->assertSuccessful();

    require base_path('app/DataHealth/Customers/'.$name.'.php');
    $class = 'App\\DataHealth\\Customers\\'.$name;

    expect(is_subclass_of($class, $contract))->toBeTrue();

    $finding = new $class(new TestModel);
    expect($finding->$method())->toBe($result);
})->with([
    'detection' => ['detect', CanDetect::class, 'detect', null],
    'verification' => ['verify', CanVerify::class, 'verify', false],
    'resolution' => ['resolve', CanResolve::class, 'resolve', false],
]);

it('generates metadata and infers capabilities required by attributes', function () {
    config()->set('data-health.make_finding.interfaces', []);

    $description = "Customer's email contains \\ or \" characters.\nReview it.";
    $this->artisan('health:make:finding', [
        'name' => 'GeneratedCompleteFinding',
        '--key' => 'duplicate-customer',
        '--description' => $description,
        '--urgency' => 'soon',
        '--worklist' => 'data-quality',
        '--schedule' => '*/5 * * * *',
        '--async' => true,
        '--queue' => 'data-health',
        '--connection' => 'redis',
        '--auto-resolve' => true,
        '--auto-verify' => '5',
    ])->assertSuccessful();

    require base_path('app/DataHealth/GeneratedCompleteFinding.php');
    $class = GeneratedCompleteFinding::class;
    $reflection = new ReflectionClass($class);

    expect(class_implements($class))->toContain(CanDetect::class, CanResolve::class, CanVerify::class)
        ->and($class::key())->toBe('duplicate-customer')
        ->and($class::getDescription())->toBe($description)
        ->and($class::getUrgency())->toBe(FindingUrgency::SOON)
        ->and($class::getWorklist())->toBe('data-quality')
        ->and($reflection->getAttributes(Scheduled::class)[0]->newInstance()->expression)->toBe('*/5 * * * *')
        ->and($reflection->getAttributes(Async::class)[0]->newInstance())->toEqual(new Async('data-health', 'redis'))
        ->and($reflection->getAttributes(AutoVerify::class)[0]->newInstance()->delay)->toBe(5)
        ->and($reflection->getAttributes(AutoResolve::class))->toHaveCount(1);
});

it('generates queued detections using the default queue routing', function () {
    $this->artisan('health:make:finding', [
        'name' => 'QueuedFinding',
        '--schedule' => '0 * * * *',
        '--async' => true,
    ])->assertSuccessful();

    expect(file_get_contents(base_path('app/DataHealth/QueuedFinding.php')))
        ->toContain('#[Async]', 'implements CanDetect');
});

it('avoids import collisions with the generated class name', function (string $name, array $options) {
    $this->artisan('health:make:finding', ['name' => 'GeneratedCollisions/'.$name, ...$options])
        ->assertSuccessful();

    require base_path('app/DataHealth/GeneratedCollisions/'.$name.'.php');

    expect(is_subclass_of('App\\DataHealth\\GeneratedCollisions\\'.$name, Finding::class))->toBeTrue();
})->with([
    'base class' => ['fINDing', []],
    'contract' => ['CanDetect', ['--detect' => true]],
    'attribute' => ['Scheduled', ['--schedule' => '0 * * * *']],
    'enum' => ['FindingUrgency', ['--urgency' => 'normal']],
]);

it('supports immediate automatic verification', function () {
    config()->set('data-health.make_finding.interfaces', []);

    $this->artisan('health:make:finding', ['name' => 'ImmediateFinding', '--auto-verify' => '0'])
        ->assertSuccessful();

    expect(file_get_contents(base_path('app/DataHealth/ImmediateFinding.php')))
        ->toContain('#[AutoVerify(0)]', 'implements CanVerify');
});

it('rejects invalid default interfaces before creating a finding', function (mixed $interfaces) {
    config()->set('data-health.make_finding.interfaces', $interfaces);

    $this->artisan('health:make:finding', ['name' => 'InvalidDefaultsFinding'])
        ->expectsOutputToContain('data-health.make_finding.interfaces')
        ->assertFailed();

    expect(is_dir(base_path('app/DataHealth')))->toBeFalse();
})->with([
    'not a list' => [CanDetect::class],
    'unknown interface' => [[Countable::class]],
    'invalid entry' => [[null]],
]);

it('uses the first configured directory and allows selecting another', function () {
    config()->set('data-health.directories', [
        'domain/Findings' => 'Domain\\Findings\\',
        'modules/Customers/Findings' => 'Modules\\Customers\\Findings\\',
    ]);

    $this->artisan('health:make:finding', ['name' => 'FirstFinding'])->assertSuccessful();
    $this->artisan('health:make:finding', [
        'name' => 'Modules\\Customers\\Findings\\Nested\\SelectedFinding',
        '--directory' => 'modules/Customers/Findings',
    ])->assertSuccessful();

    expect(file_get_contents(base_path('domain/Findings/FirstFinding.php')))
        ->toContain('namespace Domain\\Findings;')
        ->and(file_get_contents(base_path('modules/Customers/Findings/Nested/SelectedFinding.php')))
        ->toContain('namespace Modules\\Customers\\Findings\\Nested;');
});

it('protects existing files unless forced', function () {
    $path = base_path('app/DataHealth/ExistingFinding.php');
    (new Filesystem)->ensureDirectoryExists(dirname($path));
    file_put_contents($path, 'existing code');

    $this->artisan('health:make:finding', ['name' => 'ExistingFinding'])
        ->expectsOutputToContain('already exists')
        ->assertFailed();

    expect(file_get_contents($path))->toBe('existing code');

    $this->artisan('health:make:finding', ['name' => 'ExistingFinding', '--force' => true])
        ->assertSuccessful();

    expect(file_get_contents($path))->toContain('class ExistingFinding extends Finding');
});

it('rejects invalid options without creating files', function (array $options, string $message) {
    $this->artisan('health:make:finding', ['name' => 'InvalidFinding', ...$options])
        ->expectsOutputToContain($message)
        ->assertFailed();

    expect(is_dir(base_path('app/DataHealth')))->toBeFalse();
})->with([
    'urgency' => [['--urgency' => 'high'], 'Invalid urgency'],
    'cron expression' => [['--schedule' => 'not a cron'], 'Invalid schedule'],
    'negative delay' => [['--auto-verify' => '-1'], 'non-negative integer'],
    'fractional delay' => [['--auto-verify' => '1.5'], 'non-negative integer'],
    'async without schedule' => [['--async' => true], '--async requires --schedule'],
    'queue without async' => [['--queue' => 'health'], '--queue and --connection require --async'],
    'connection without async' => [['--connection' => 'redis'], '--queue and --connection require --async'],
    'unknown directory' => [['--directory' => 'unknown'], 'not configured'],
]);

it('rejects invalid class names without creating files', function (string $name) {
    $this->artisan('health:make:finding', ['name' => $name])
        ->expectsOutputToContain('Invalid finding class name')
        ->assertFailed();

    expect(is_dir(base_path('app/DataHealth')))->toBeFalse();
})->with(['../OutsideFinding', 'Bad-Name', 'Customers/class', '123Finding', 'int', 'mixed', '']);

it('requires a configured discovery directory', function () {
    config()->set('data-health.directories', []);

    $this->artisan('health:make:finding', ['name' => 'MissingDirectoryFinding'])
        ->expectsOutputToContain('Configure at least one finding directory')
        ->assertFailed();
});
