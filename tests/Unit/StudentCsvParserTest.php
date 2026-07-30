<?php

namespace Tests\Unit;

use App\Services\StudentCsvParser;
use App\Services\StudentImportPreparation;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StudentCsvParserTest extends TestCase
{
    public function test_parser_supports_semicolon_and_tab_delimiters_deterministically(): void
    {
        $parser = app(StudentCsvParser::class);
        $semicolon = $parser->parse(
            "student_number;first_name;last_name\n1;Ana;Cruz\n",
        );
        $tab = $parser->parse(
            "student_number\tfirst_name\tlast_name\n2\tLourdes\tReyes\n",
        );

        $this->assertSame(';', $semicolon['delimiter']);
        $this->assertSame('Ana', $semicolon['rows'][0]['values'][1]);
        $this->assertSame("\t", $tab['delimiter']);
        $this->assertSame('Lourdes', $tab['rows'][0]['values'][1]);
    }

    public function test_parser_reports_structural_row_errors_for_preview(): void
    {
        $parsed = app(StudentCsvParser::class)->parse(
            "student_number,first_name,last_name\n1,Ana\n",
        );

        $this->assertSame(['1', 'Ana', ''], $parsed['rows'][0]['values']);
        $this->assertSame(
            ['Expected 3 columns but found 2.'],
            $parsed['rows'][0]['issues'],
        );
    }

    public function test_parser_rejects_non_utf8_input(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The CSV file must use UTF-8 encoding.');

        app(StudentCsvParser::class)->parse(
            "student_number,first_name\n1,\xFF\n",
        );
    }

    public function test_parser_preserves_unicode_and_escaped_quotes(): void
    {
        $parsed = app(StudentCsvParser::class)->parse(
            "student_number,first_name,last_name\n1,\"Ana \"\"Mari\"\"\",Dela Peña\n",
        );

        $this->assertSame('Ana "Mari"', $parsed['rows'][0]['values'][1]);
        $this->assertSame('Dela Peña', $parsed['rows'][0]['values'][2]);
    }

    public function test_mapping_suggestions_are_exact_and_leave_ambiguous_headers_unmapped(): void
    {
        $mapping = app(StudentImportPreparation::class)->suggestedMapping([
            'Student Number',
            'First Name',
            'Program',
            'Name',
            'Status',
        ]);

        $this->assertSame([
            'student_number',
            'first_name',
            'program',
            null,
            null,
        ], $mapping);
    }
}
