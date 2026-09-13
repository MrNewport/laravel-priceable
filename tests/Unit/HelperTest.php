<?php

it('keeps its namespaced helper available beside an application helper', function () {
    $autoload = var_export(__DIR__.'/../../vendor/autoload.php', true);
    $helper = var_export(__DIR__.'/../../src/Support/Helpers.php', true);
    $code = 'function price_for() { return "application"; } require '.$autoload.'; require '.$helper.'; '
        .'if (!function_exists("MrNewport\\\\LaravelPriceable\\\\Support\\\\price_for")) exit(2); echo price_for();';
    $process = new \Symfony\Component\Process\Process([PHP_BINARY, '-r', $code]);
    $process->mustRun();
    expect($process->getOutput())->toBe('application');
});
