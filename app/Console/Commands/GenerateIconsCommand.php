<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\AppIcon;
use Illuminate\Console\Command;

class GenerateIconsCommand extends Command
{
    protected $signature = 'takt:icons';

    protected $description = 'Render the notification icon as a PNG';

    private const array SIZES = [192];

    public function handle(): int
    {
        if (! AppIcon::supported()) {
            $this->components->error('The GD extension is required to render the icons.');

            return self::FAILURE;
        }

        foreach (self::SIZES as $size) {
            $path = public_path(sprintf('icons/icon-%d.png', $size));

            AppIcon::write($size, $path);

            $this->components->twoColumnDetail(basename($path), $size.'×'.$size);
        }

        return self::SUCCESS;
    }
}
