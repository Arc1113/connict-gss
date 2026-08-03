<?php

namespace Tests\Feature\EnhancementTts;

use App\Services\FormatAnnouncementAwardList;

class AnnouncementAwardFormatterTest extends AnnouncementTestCase
{
    public function test_zero_one_two_and_many_awards_use_the_approved_grammar(): void
    {
        $formatter = app(FormatAnnouncementAwardList::class);
        $awards = [
            $this->award('Research', 'the Research Award', 0, 1),
            $this->award('Leadership', 'the Leadership Award', 1, 2),
            $this->award('Capstone', 'the Capstone Award', 2, 3),
            $this->award('Service', 'the Service Award', 3, 4),
        ];

        $this->assertSame('', $formatter->format([], 'recipient of')->text);
        $this->assertSame(
            'recipient of the Research Award',
            $formatter->format(array_slice($awards, 0, 1), 'recipient of')->text,
        );
        $this->assertSame(
            'recipient of the Research Award and the Leadership Award',
            $formatter->format(array_slice($awards, 0, 2), 'recipient of')->text,
        );
        $this->assertSame(
            'recipient of the Research Award, the Leadership Award, and the Capstone Award',
            $formatter->format(array_slice($awards, 0, 3), 'recipient of')->text,
        );
        $this->assertSame(
            'recipient of the Research Award, the Leadership Award, the Capstone Award, and the Service Award',
            $formatter->format($awards, 'recipient of')->text,
        );
    }

    public function test_order_is_display_order_then_identifier_and_disabled_awards_are_omitted(): void
    {
        $result = app(FormatAnnouncementAwardList::class)->format([
            $this->award('Third', null, 2, 10),
            $this->award('Disabled', null, 0, 1, false),
            $this->award('Second', null, 1, 8),
            $this->award('First', null, 1, 7),
        ], null);

        $this->assertSame('First, Second, and Third', $result->text);
        $this->assertSame(3, $result->awardCount);
    }

    public function test_spoken_text_is_preferred_and_name_is_the_fallback(): void
    {
        $result = app(FormatAnnouncementAwardList::class)->format([
            $this->award('Administrative Name', '  reviewed spoken wording  ', 0, 1),
            $this->award('Fallback Name', " \n ", 1, 2),
        ], null);

        $this->assertSame('reviewed spoken wording and Fallback Name', $result->text);
    }

    public function test_duplicate_empty_lead_in_and_list_breaking_awards_block(): void
    {
        $result = app(FormatAnnouncementAwardList::class)->format([
            $this->award('Research', 'the Research Award', 0, 1),
            $this->award('Duplicate', ' THE research award. ', 1, 2),
            $this->award('', null, 2, 3),
            $this->award('Legacy', 'recipient of the Legacy Award', 3, 4),
            $this->award('Conjunction', 'and the Service Award', 4, 5),
        ], 'recipient of');

        $codes = $this->issueCodes($result->blockingErrors);
        $this->assertContains('duplicate_award_phrase', $codes);
        $this->assertContains('empty_enabled_award', $codes);
        $this->assertContains('award_contains_lead_in', $codes);
        $this->assertContains('award_not_list_ready', $codes);
        $this->assertSame('recipient of the Research Award', $result->text);
    }

    public function test_unicode_and_xml_characters_remain_plain_text_for_safe_later_escaping(): void
    {
        $result = app(FormatAnnouncementAwardList::class)->format([
            $this->award('Paglilingkod & Pananaliksik', 'the Niño <Research> Award', 0, 1),
        ], 'recipient of');

        $this->assertSame('recipient of the Niño <Research> Award', $result->text);
        $this->assertSame([], $result->blockingErrors);
    }
}
