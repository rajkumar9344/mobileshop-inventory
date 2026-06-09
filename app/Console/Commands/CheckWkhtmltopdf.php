<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class CheckWkhtmltopdf extends Command
{
    protected $signature = 'check:wkhtmltopdf';
    protected $description = 'Check if wkhtmltopdf binary is configured correctly and executable';

    public function handle()
    {
        $binary = env('WKHTML_PDF_BINARY');

        if (!$binary) {
            $this->error('WKHTML_PDF_BINARY is not set in your .env file.');
            return 1;
        }

        $this->info("Using binary: $binary");

        $process = Process::fromShellCommandline("$binary --version");

        try {
            $process->mustRun();
            $this->info('✅ wkhtmltopdf is working:');
            $this->line($process->getOutput());
        } catch (\Exception $e) {
            $this->error('❌ Error running wkhtmltopdf:');
            $this->line($e->getMessage());
            $this->line($process->getErrorOutput());
            return 1;
        }

        return 0;
    }
}
