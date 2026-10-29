<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Catalogue\Import;

defined('ABSPATH') || exit;

/**
 * The whole-file validation pass: one outcome per data row, with its reason, its store record and every issue.
 *
 * Order never decides identity. Repeated SKUs are found first across the whole file and every affected row is
 * rejected (R2), never "the first one wins". Parents are resolved after every non-variation row has its outcome, so a
 * parent written below its variations counts, and a rejected or non-variable parent row does not. Nothing is written
 * to the catalogue; the command runs this inside `CatalogueWriteGuard`.
 *
 * Register ids: MIG-06, MIG-19. See specs/006-catalogue-import-spec, FR-005, FR-006, FR-009, FR-012.
 */
final class ValidationPass
{
    /**
     * @param array{header:list<string>,rows:list<array{row:int,cells:list<string>}>} $read
     *
     * @return array{lines:list<array<string,string>>,client_issues:list<array<string,string>>,counts:array<string,int>}
     */
    public static function run(Mapping $mapping, array $read): array
    {
        $rows  = array_map(static fn (array $raw): array => RowInterpreter::interpret($raw, $read['header'], $mapping), $read['rows']);
        $index = SkuIndex::build(array_merge(array_column($rows, 'sku_source'), array_column($rows, 'parent_sku_source')));
        $rows  = self::markRepeatedSkus($rows, $mapping, $index);

        $decided = [];
        foreach ($rows as $position => $row) {
            if ($row['type'] !== 'variation') {
                $decided[$position] = self::decide($row, $index, null, $mapping);
            }
        }
        $fileParents = self::fileParents($rows, $decided, $index);
        foreach ($rows as $position => $row) {
            if ($row['type'] === 'variation') {
                $decided[$position] = self::decide($row, $index, self::parent($row, $fileParents, $index), $mapping);
            }
        }
        ksort($decided);

        $lines        = [];
        $clientIssues = [];
        $counts       = ['rows' => count($rows), 'create' => 0, 'update' => 0, 'reject' => 0, 'client_issues' => 0, 'developer_issues' => 0];
        foreach ($decided as $position => $decision) {
            $row = $rows[$position];
            ++$counts[$decision['outcome']];
            foreach ($decision['issues'] as $issue) {
                ++$counts[$issue['owner'] === 'client' ? 'client_issues' : 'developer_issues'];
                if ($issue['owner'] === 'client') {
                    $clientIssues[] = [
                        'row'        => (string) $row['row'],
                        'sku'        => $row['sku_source'],
                        'field'      => $issue['field'],
                        'problem'    => $issue['problem'],
                        'correction' => $issue['correction'],
                        'issue_code' => $issue['code'],
                    ];
                }
            }
            $lines[] = self::line($row, $decision);
        }

        return ['lines' => $lines, 'client_issues' => $clientIssues, 'counts' => $counts];
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed>|null $parent
     *
     * @return array<string, mixed>
     */
    private static function decide(array $row, SkuIndex $index, ?array $parent, Mapping $mapping): array
    {
        $resolution = $row['sku'] === '' ? ['status' => 'none', 'candidates' => []] : $index->resolve($row['sku_source']);
        $decision   = LoadRules::decide($row, $resolution, $parent, $mapping);

        return $decision + ['resolution' => $resolution];
    }

    /**
     * Every row whose SKU repeats another row's, by the store's comparison, is rejected with the related rows named.
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    private static function markRepeatedSkus(array $rows, Mapping $mapping, SkuIndex $index): array
    {
        $byKey = [];
        foreach ($rows as $position => $row) {
            if ($row['sku'] !== '') {
                $byKey[$index->key($row['sku_source'])][] = $position;
            }
        }
        foreach ($byKey as $positions) {
            if (count($positions) < 2) {
                continue;
            }
            $numbers = array_map(static fn (int $p): int => $rows[$p]['row'], $positions);
            foreach ($positions as $position) {
                $rows[$position]['issues'][] = LoadRules::issue('R2', (string) $mapping->columnFor('sku'), sprintf(
                    /* translators: %s: row numbers. */
                    __('The SKU is repeated on rows %s, so which row is the product cannot be decided.', 'mizzey-site'),
                    implode(', ', $numbers),
                ), __('Give each product its own SKU, or remove the repeated rows.', 'mizzey-site'));
            }
        }

        return $rows;
    }

    /**
     * File rows that could serve as a parent, by SKU key: each with whether it is usable.
     *
     * @param list<array<string, mixed>>        $rows
     * @param array<int, array<string, mixed>> $decided
     *
     * @return array<string, list<array{row:int,usable:bool,type:string,owner:string}>>
     */
    private static function fileParents(array $rows, array $decided, SkuIndex $index): array
    {
        $parents = [];
        foreach ($decided as $position => $decision) {
            $row = $rows[$position];
            if ($row['sku'] === '') {
                continue;
            }
            $parents[$index->key($row['sku_source'])][] = [
                'row'    => $row['row'],
                'usable' => $row['type'] === 'variable' && $decision['outcome'] !== 'reject',
                'type'   => (string) $row['type'],
                // A parent rejected for a store-identity defect leaves the Developer, not the Client, to correct it.
                'owner'  => in_array('developer', array_column(array_filter($decision['issues'], static fn (array $i): bool => str_starts_with($i['code'], 'R')), 'owner'), true) ? 'developer' : 'client',
            ];
        }

        return $parents;
    }

    /**
     * @param array<string, mixed>                                   $row
     * @param array<string, list<array{row:int,usable:bool,type:string,owner:string}>> $fileParents
     *
     * @return array{usable:bool,reason:string,correction:string,owner:string}
     */
    private static function parent(array $row, array $fileParents, SkuIndex $index): array
    {
        $usable = ['usable' => true, 'reason' => '', 'correction' => '', 'owner' => 'client'];
        if ($row['parent_sku'] === '') {
            return $usable;
        }
        $inFile = $fileParents[$index->key($row['parent_sku_source'])] ?? [];
        if ($inFile !== []) {
            if (count($inFile) === 1 && $inFile[0]['usable']) {
                return $usable;
            }
            $reason = count($inFile) > 1
                ? __('The parent SKU is on more than one row of the file.', 'mizzey-site')
                : ($inFile[0]['type'] !== 'variable'
                    ? sprintf(
                        /* translators: 1: a row number, 2: its row type. */
                        __('The parent SKU is on row %1$d, which is a %2$s product, not a variable one.', 'mizzey-site'),
                        $inFile[0]['row'],
                        $inFile[0]['type'],
                    )
                    : sprintf(
                        /* translators: %d: a row number. */
                        __('The parent on row %d is rejected, so it cannot hold variations.', 'mizzey-site'),
                        $inFile[0]['row'],
                    ));

            $owner = count($inFile) === 1 && $inFile[0]['type'] === 'variable' ? $inFile[0]['owner'] : 'client';

            return ['usable' => false, 'reason' => $reason, 'correction' => __('Resolve the issues of the parent row first.', 'mizzey-site'), 'owner' => $owner];
        }

        $store = $index->resolve($row['parent_sku_source']);
        if ($store['status'] === 'original' && $store['kind'] === 'product' && $store['product_type'] === 'variable') {
            return $usable;
        }
        if ($store['status'] === 'none') {
            return ['usable' => false, 'reason' => __('The parent SKU is neither in the file nor in the store.', 'mizzey-site'), 'correction' => __('Add the parent product row, or correct the parent SKU.', 'mizzey-site'), 'owner' => 'client'];
        }

        return ['usable' => false, 'reason' => __('The parent SKU belongs to a store record that cannot hold variations (not a variable product, or its identity is unresolved).', 'mizzey-site'), 'correction' => __('Resolve the store record of the parent before the load.', 'mizzey-site'), 'owner' => 'developer'];
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $decision
     *
     * @return array<string, string>
     */
    private static function line(array $row, array $decision): array
    {
        $resolution = $decision['resolution'];
        $near       = array_map(
            static fn (array $c): string => sprintf('%d=%s (%s)', $c['id'], $c['stored_sku'], $c['comparison']),
            array_filter($resolution['candidates'] ?? [], static fn (array $c): bool => $c['comparison'] !== 'exact'),
        );
        $role = '';
        if (($resolution['status'] ?? '') === 'original') {
            $role = 'original';
        } elseif (in_array('translation', array_column($resolution['candidates'] ?? [], 'role'), true)) {
            $role = 'translation';
        }

        return [
            'row'           => (string) $row['row'],
            'sku'           => $row['sku_source'],
            'type'          => (string) ($row['type'] ?? $row['type_source']),
            'outcome'       => $decision['outcome'],
            'rule'          => $decision['rule'],
            'reason'        => $decision['reason'],
            'resolved_id'   => isset($resolution['id']) ? (string) $resolution['id'] : '',
            'language_role' => $role,
            'translations'  => implode(' ', $resolution['translations'] ?? []),
            'issues'        => implode(' ', array_unique(array_column($decision['issues'], 'code'))),
            'near_matches'  => implode('; ', $near),
        ];
    }
}
