<?php

declare(strict_types=1);

namespace DataHealth\Console\Commands;

use Cron\CronExpression;
use DataHealth\Contracts\CanDetect;
use DataHealth\Contracts\CanResolve;
use DataHealth\Contracts\CanVerify;
use DataHealth\Enums\FindingUrgency;
use Illuminate\Console\GeneratorCommand;
use InvalidArgumentException;

class MakeFindingCommand extends GeneratorCommand
{
    /** @var string */
    protected $signature = 'health:make:finding
        {name : The finding class name, optionally including a subnamespace}
        {--detect : Add detection logic}
        {--verify : Add verification logic}
        {--resolve : Add resolution logic}
        {--key= : The stable finding key}
        {--description= : A description of the finding}
        {--urgency= : immediate, soon, normal, or deferred}
        {--worklist= : The worklist for new records}
        {--schedule= : A cron expression (also adds detection)}
        {--async : Queue detection (also adds detection)}
        {--queue= : The detection queue}
        {--connection= : The detection queue connection}
        {--auto-resolve : Automatically resolve new records (also adds resolution)}
        {--auto-verify= : Automatically verify new records after this many minutes (also adds verification)}
        {--directory= : A directory configured in data-health.directories (defaults to the first)}
        {--f|force : Overwrite an existing finding class}';

    /** @var string */
    protected $description = 'Create a new Data Health finding class';

    /** @var string */
    protected $type = 'Finding';

    private string $directory;

    private string $namespace;

    /** @var list<class-string> */
    private array $defaultInterfaces = [];

    public function handle(): ?bool
    {
        try {
            $this->configureDirectory();
            $this->validateOptions();
        } catch (InvalidArgumentException $exception) {
            $this->fail($exception->getMessage());
        }

        if (parent::handle() === false) {
            $this->fail('Finding could not be created.');
        }

        return null;
    }

    protected function getStub(): string
    {
        return __DIR__.'/../../../resources/stubs/finding.stub';
    }

    /** @param string $name */
    protected function qualifyClass(mixed $name): string
    {
        $name = str_replace('/', '\\', ltrim($name, '\\/'));

        return str_starts_with($name, $this->namespace.'\\')
            ? $name
            : $this->namespace.'\\'.$name;
    }

    protected function rootNamespace(): string
    {
        return $this->namespace.'\\';
    }

    /** @param string $name */
    protected function getPath(mixed $name): string
    {
        $relativeClass = substr($name, strlen($this->namespace) + 1);

        return base_path($this->directory).'/'.str_replace('\\', '/', $relativeClass).'.php';
    }

    /** @param string $name */
    protected function buildClass(mixed $name): string
    {
        $imports = [];
        $attributes = [];
        $contracts = [];
        $methods = [];

        // Alias imports when the generated class has the same name.
        $import = function (string $class) use (&$imports, $name): string {
            $alias = class_basename($class);

            if (strcasecmp($alias, class_basename($name)) === 0) {
                $alias = 'Base'.$alias;
            }

            $imports[] = 'use '.$class.($alias === class_basename($class) ? '' : ' as '.$alias).';';

            return $alias;
        };

        $baseClass = $import('DataHealth\\Finding');

        foreach (['key' => 'Key', 'description' => 'Description', 'worklist' => 'Worklist', 'schedule' => 'Scheduled'] as $option => $attribute) {
            if (($value = $this->stringOption($option)) !== null) {
                $attribute = $import('DataHealth\\Attributes\\'.$attribute);
                $attributes[] = '#['.$attribute.'('.$this->phpString($value).')]';
            }
        }

        if (($urgency = $this->stringOption('urgency')) !== null) {
            $attribute = $import('DataHealth\\Attributes\\Urgency');
            $enum = $import(FindingUrgency::class);
            $attributes[] = '#['.$attribute.'('.$enum.'::'.strtoupper($urgency).')]';
        }

        $asyncAttribute = null;

        if ($this->option('async')) {
            $attribute = $import('DataHealth\\Attributes\\Async');
            $arguments = [];

            foreach (['queue', 'connection'] as $option) {
                if (($value = $this->stringOption($option)) !== null) {
                    $arguments[] = $option.': '.$this->phpString($value);
                }
            }

            $asyncAttribute = '#['.$attribute.($arguments === [] ? '' : '('.implode(', ', $arguments).')').']';
        }

        if ($this->option('auto-resolve')) {
            $attributes[] = '#['.$import('DataHealth\\Attributes\\AutoResolve').']';
        }

        if ($this->option('auto-verify') !== null) {
            $attributes[] = '#['.$import('DataHealth\\Attributes\\AutoVerify').'('.(int) $this->option('auto-verify').')]';
        }

        $capabilities = [
            'detect' => in_array(CanDetect::class, $this->defaultInterfaces, true) || $this->option('detect') || $this->option('schedule') !== null || $this->option('async'),
            'verify' => in_array(CanVerify::class, $this->defaultInterfaces, true) || $this->option('verify') || $this->option('auto-verify') !== null,
            'resolve' => in_array(CanResolve::class, $this->defaultInterfaces, true) || $this->option('resolve') || $this->option('auto-resolve'),
        ];

        foreach ($capabilities as $method => $enabled) {
            if ($enabled) {
                $contracts[] = $import('DataHealth\\Contracts\\Can'.ucfirst($method));
                $source = rtrim($this->files->get(__DIR__.'/../../../resources/stubs/finding.'.$method.'.stub'));
                $methods[] = ($method === 'detect' && $asyncAttribute !== null ? '    '.$asyncAttribute."\n" : '').$source;
            }
        }

        return strtr(parent::buildClass($name), [
            '{{ imports }}' => implode("\n", $imports),
            '{{ attributes }}' => $attributes === [] ? '' : implode("\n", $attributes)."\n",
            '{{ baseClass }}' => $baseClass,
            '{{ contracts }}' => $contracts === [] ? '' : ' implements '.implode(', ', $contracts),
            '{{ body }}' => $methods === [] ? ' {}' : "\n{\n".implode("\n\n", $methods)."\n}",
        ]);
    }

    private function configureDirectory(): void
    {
        /** @var array<string, string> $directories */
        $directories = config('data-health.directories', []);
        $directory = $this->stringOption('directory') ?? array_key_first($directories);

        if ($directory === null) {
            throw new InvalidArgumentException('Configure at least one finding directory in data-health.directories.');
        }

        if (! array_key_exists($directory, $directories)) {
            throw new InvalidArgumentException('Finding directory is not configured: '.$directory);
        }

        $this->directory = $directory;
        $this->namespace = trim($directories[$directory], '\\');
    }

    private function validateOptions(): void
    {
        $interfaces = config('data-health.make_finding.interfaces', [CanDetect::class]);

        if (! is_array($interfaces) || ! array_is_list($interfaces)) {
            throw new InvalidArgumentException('data-health.make_finding.interfaces must be a list of supported finding interfaces.');
        }

        foreach ($interfaces as $interface) {
            if (! in_array($interface, [CanDetect::class, CanVerify::class, CanResolve::class], true)) {
                throw new InvalidArgumentException('data-health.make_finding.interfaces only supports CanDetect::class, CanVerify::class, and CanResolve::class from DataHealth\\Contracts.');
            }
        }

        $this->defaultInterfaces = $interfaces;
        $name = str_replace('/', '\\', ltrim($this->getNameInput(), '\\'));

        foreach (explode('\\', $this->namespace.'\\'.$name) as $segment) {
            if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $segment) || $this->isReservedName($segment)) {
                throw new InvalidArgumentException('Invalid finding class name: '.$this->getNameInput());
            }
        }

        if (in_array(strtolower(class_basename($name)), ['bool', 'float', 'int', 'iterable', 'mixed', 'never', 'null', 'object', 'string', 'void'], true)) {
            throw new InvalidArgumentException('Invalid finding class name: '.$this->getNameInput());
        }

        if (($urgency = $this->stringOption('urgency')) !== null && FindingUrgency::tryFrom($urgency) === null) {
            throw new InvalidArgumentException('Invalid urgency. Use immediate, soon, normal, or deferred.');
        }

        if (($schedule = $this->stringOption('schedule')) !== null && ! CronExpression::isValidExpression($schedule)) {
            throw new InvalidArgumentException('Invalid schedule. Provide a valid cron expression.');
        }

        if ($this->option('auto-verify') !== null && filter_var($this->option('auto-verify'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false) {
            throw new InvalidArgumentException('The automatic verification delay must be a non-negative integer.');
        }

        if (($this->option('queue') !== null || $this->option('connection') !== null) && ! $this->option('async')) {
            throw new InvalidArgumentException('--queue and --connection require --async.');
        }
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        if ($value !== null && ! is_string($value)) {
            throw new InvalidArgumentException('--'.$name.' requires a string value.');
        }

        return $value;
    }

    private function phpString(string $value): string
    {
        return "'".addcslashes($value, "\\'")."'";
    }
}
