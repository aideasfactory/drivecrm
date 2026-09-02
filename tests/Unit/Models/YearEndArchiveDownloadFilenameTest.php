<?php

declare(strict_types=1);

use App\Models\YearEndArchive;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

it('hyphenates the tax-year slash so the zip attachment name is valid', function () {
    $archive = new YearEndArchive(['tax_year_start' => 2025]);

    expect($archive->taxYearLabel())->toBe('2025/26')
        ->and($archive->downloadFilename())->toBe('drive-tax-archive-2025-26.zip');

    expect(
        (new ResponseHeaderBag)->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $archive->downloadFilename(),
        ),
    )->toContain('drive-tax-archive-2025-26.zip');
});

it('rejects the raw tax-year label as a content-disposition filename', function () {
    $archive = new YearEndArchive(['tax_year_start' => 2025]);

    expect(fn () => (new ResponseHeaderBag)->makeDisposition(
        ResponseHeaderBag::DISPOSITION_ATTACHMENT,
        sprintf('drive-tax-archive-%s.zip', $archive->taxYearLabel()),
    ))->toThrow(InvalidArgumentException::class);
});
