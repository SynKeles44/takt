<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\ShellEnvironment;
use Illuminate\Support\Facades\Process;

/**
 * `npm run build`, started from the page.
 *
 * The banner that says the interface is older than the code used to hand the reader a command
 * to type. Takt already knows where the project is and where the tools are, so it can run the
 * build itself; the page reloads afterwards and picks up the new asset names.
 */
final class FrontendBuild
{
    public const int TIMEOUT = 180;

    public function __construct(
        private readonly ?string $npm,
        private readonly string $root,
    ) {}

    public static function forApp(): self
    {
        return new self(ShellEnvironment::binary('npm'), base_path());
    }

    /** @return array{ok: bool, reason: string} */
    public function run(): array
    {
        if ($this->npm === null) {
            return ['ok' => false, 'reason' => __('app.build.no_npm')];
        }

        $result = Process::path($this->root)
            ->timeout(self::TIMEOUT)
            ->env(ShellEnvironment::variables())
            ->run([$this->npm, 'run', 'build', '--silent']);

        if ($result->successful()) {
            return ['ok' => true, 'reason' => ''];
        }

        // the last lines are where vite names the file and the line that broke
        $lines = preg_split('/\R/', trim($result->errorOutput().PHP_EOL.$result->output())) ?: [];

        return ['ok' => false, 'reason' => implode(' ', array_slice(array_filter($lines), -3))];
    }
}
