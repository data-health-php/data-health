<?php

declare(strict_types=1);

namespace DataHealth\Console\Prompts\Renderers;

use DataHealth\Console\Prompts\RecordTablePrompt;
use Laravel\Prompts\Themes\Contracts\Scrolling;
use Laravel\Prompts\Themes\Default\Renderer;
use Symfony\Component\Console\Formatter\OutputFormatter;

class RecordTableRenderer extends Renderer implements Scrolling
{
    public function __invoke(RecordTablePrompt $prompt): string
    {
        $terminalWidth = $prompt->terminal()->cols();

        if (in_array($prompt->state, ['submit', 'cancel'], true)) {
            return (string) $this->line($this->dim($this->cell(
                $prompt->state === 'cancel' ? 'Cancelled.' : ($prompt->action === 'solve' ? 'Mark as solved' : ucfirst($prompt->action)),
                $terminalWidth,
            )));
        }

        $headers = ['ID', 'Finding', 'Model', 'Status', 'Urgency', 'Actions'];

        // Keep identity visible first when the terminal cannot fit every column.
        foreach ([2, 4, 5, 3] as $column) {
            if (array_sum(array_map('strlen', $headers)) + 7 + count($headers) * 3 <= $terminalWidth) {
                break;
            }

            unset($headers[$column]);
        }

        $widths = [];

        foreach ($headers as $column => $header) {
            $widths[$column] = mb_strwidth($header);

            foreach ($prompt->rows as $row) {
                $widths[$column] = max($widths[$column], min(30, mb_strwidth($this->clean($row[$column] ?? ''))));
            }
        }

        $available = max(count($headers), $terminalWidth - 7 - count($headers) * 3);

        while (array_sum($widths) > $available) {
            $largest = max($widths);

            foreach ($widths as $column => $width) {
                if ($width === $largest) {
                    $widths[$column]--;

                    break;
                }
            }
        }

        $this->line(' '.$this->cyan($this->cell($prompt->label, max(1, $terminalWidth - 2))));
        $this->line('      '.$this->border($widths, '┌', '┬', '┐'));
        $this->line('      '.$this->row($headers, $widths));
        $this->line('──────'.$this->border($widths, '┼', '┼', '┤'));

        foreach ($prompt->visible() as $id => $label) {
            $selected = in_array($id, $prompt->value(), true);
            $highlighted = $id === $prompt->highlightedValue();
            $pointer = ($highlighted ? '› ' : '  ').($selected ? '[x] ' : '[ ] ');
            $line = $pointer.$this->row($prompt->rows[$id], $widths);
            $this->line($highlighted ? $this->inverse($line) : $line);
        }

        $this->line('      '.$this->border($widths, '└', '┴', '┘'));
        $this->line($this->dim($this->cell(count($prompt->value()).' selected · i ignore · s mark solved', $terminalWidth)));
        $this->line($this->dim($this->cell('v verify · r resolve · Enter inspect', $terminalWidth)));
        $this->line($this->dim($this->cell('↑↓ move · Space select · a all', $terminalWidth)));
        $this->line($this->dim($this->cell('n/p pages · f refresh · q exit', $terminalWidth)));

        return (string) $this;
    }

    public function reservedLines(): int
    {
        return 11;
    }

    /** @param array<int, int> $widths */
    private function border(array $widths, string $left, string $middle, string $right): string
    {
        return $this->dim($left.implode($middle, array_map(fn (int $width): string => str_repeat('─', $width + 2), $widths)).$right);
    }

    /**
     * @param  array<int, string>  $values
     * @param  array<int, int>  $widths
     */
    private function row(array $values, array $widths): string
    {
        $cells = [];

        foreach ($widths as $column => $width) {
            $cells[] = ' '.$this->cell($values[$column] ?? '', $width).' ';
        }

        return '│'.implode('│', $cells).'│';
    }

    private function cell(string $text, int $width): string
    {
        $text = mb_strimwidth($this->clean($text), 0, $width, '…');

        return OutputFormatter::escape($text.str_repeat(' ', max(0, $width - mb_strwidth($text))));
    }

    private function clean(string $text): string
    {
        $text = preg_replace('/\e\[[0-?]*[ -\/]*[@-~]/', '', $text) ?? '';

        return preg_replace('/[\x00-\x1F\x7F]/u', '', $text) ?? '';
    }
}
