<?php

namespace App\AiBundle\Service;

class AiAnalyzer
{
    public function analyze(array $data): array
    {
        $result = [];

        foreach ($data as $item) {
            if ($item['total'] >= 5) {
                $result[] = sprintf('%s is very popular', $item['nom']);
            } elseif ($item['total'] > 0) {
                $result[] = sprintf('%s is growing', $item['nom']);
            } else {
                $result[] = sprintf('%s has low activity', $item['nom']);
            }
        }

        return $result;
    }

    public function globalInsight(array $data): string
    {
        $sum = array_sum(array_column($data, 'total'));

        if ($sum > 20) {
            return 'Overall activity is strong';
        }

        return 'Overall activity is low';
    }
}
