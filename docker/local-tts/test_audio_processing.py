import unittest

import numpy as np

from audio_processing import SAMPLE_RATE, join_sections, normalize_silence


class AudioProcessingTest(unittest.TestCase):
    def test_sections_are_joined_for_one_contextual_utterance(self):
        self.assertEqual(
            "We proudly present, Maria Santos, magna cum laude.",
            join_sections(
                ["We proudly present,", " Maria Santos, ", "magna cum laude."],
                "unused fallback",
            ),
        )

    def test_plain_text_is_used_when_sections_are_absent(self):
        self.assertEqual(
            "Fictional announcement.",
            join_sections([], " Fictional announcement. "),
        )

    def test_long_internal_silence_is_capped_and_edges_are_trimmed(self):
        leading = np.zeros(int(SAMPLE_RATE * 0.8), dtype=np.float32)
        first = np.full(int(SAMPLE_RATE * 0.2), 0.25, dtype=np.float32)
        internal = np.zeros(int(SAMPLE_RATE * 0.9), dtype=np.float32)
        second = np.full(int(SAMPLE_RATE * 0.2), -0.25, dtype=np.float32)
        trailing = np.zeros(int(SAMPLE_RATE * 0.8), dtype=np.float32)

        normalized = normalize_silence(
            np.concatenate([leading, first, internal, second, trailing]),
        )

        expected_seconds = 0.1 + 0.2 + 0.25 + 0.2 + 0.1
        self.assertEqual(round(expected_seconds * SAMPLE_RATE), normalized.size)

    def test_normal_speech_gap_is_preserved(self):
        first = np.full(int(SAMPLE_RATE * 0.2), 0.25, dtype=np.float32)
        internal = np.zeros(int(SAMPLE_RATE * 0.3), dtype=np.float32)
        second = np.full(int(SAMPLE_RATE * 0.2), -0.25, dtype=np.float32)

        normalized = normalize_silence(np.concatenate([first, internal, second]))

        self.assertEqual(first.size + internal.size + second.size, normalized.size)


if __name__ == "__main__":
    unittest.main()
