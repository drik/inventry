<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $report->title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #333; margin: 30px; }
        h1 { font-size: 20px; margin-bottom: 5px; }
        h2 { font-size: 15px; color: #555; margin-top: 25px; border-bottom: 2px solid #e5e7eb; padding-bottom: 5px; }
        .header { border-bottom: 3px solid #3b82f6; padding-bottom: 15px; margin-bottom: 20px; }
        .meta { color: #666; font-size: 10px; }
        .stats-grid { display: flex; gap: 15px; margin: 15px 0; }
        .stat-box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px 15px; text-align: center; flex: 1; }
        .stat-value { font-size: 22px; font-weight: bold; }
        .stat-label { font-size: 9px; color: #666; text-transform: uppercase; }
        .stat-found .stat-value { color: #22c55e; }
        .stat-missing .stat-value { color: #ef4444; }
        .stat-unexpected .stat-value { color: #f59e0b; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 10px; }
        th { background: #f3f4f6; padding: 8px 6px; text-align: left; border-bottom: 2px solid #d1d5db; }
        td { padding: 6px; border-bottom: 1px solid #e5e7eb; }
        tr:nth-child(even) { background: #fafafa; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 9px; font-weight: bold; }
        .badge-found { background: #dcfce7; color: #166534; }
        .badge-missing { background: #fecaca; color: #991b1b; }
        .badge-unexpected { background: #fef3c7; color: #92400e; }
        .badge-expected { background: #e5e7eb; color: #374151; }
        .summary { background: #eff6ff; border-left: 4px solid #3b82f6; padding: 12px 15px; margin: 15px 0; font-size: 11px; line-height: 1.6; }
        .footer { margin-top: 30px; text-align: center; font-size: 9px; color: #999; border-top: 1px solid #e5e7eb; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $report->title }}</h1>
        <div class="meta">
            Organisation : {{ $organization->name }} &nbsp;|&nbsp;
            Session : {{ $session->name }} &nbsp;|&nbsp;
            @if($task)
                Lieu : {{ $task->location?->name ?? '—' }} &nbsp;|&nbsp;
                Agent : {{ $task->assignee?->name ?? '—' }} &nbsp;|&nbsp;
            @endif
            Généré le : {{ $report->created_at->format('d/m/Y à H:i') }}
        </div>
    </div>

    @if($report->summary)
        <h2>Résumé</h2>
        <div class="summary">{{ $report->summary }}</div>
    @endif

    <h2>Statistiques</h2>
    <table>
        <tr>
            <td style="text-align: center; width: 20%; padding: 12px;">
                <div style="font-size: 24px; font-weight: bold;">{{ $stats['total_expected'] }}</div>
                <div style="font-size: 9px; color: #666;">ATTENDUS</div>
            </td>
            <td style="text-align: center; width: 20%; padding: 12px;">
                <div style="font-size: 24px; font-weight: bold; color: #22c55e;">{{ $stats['total_found'] }}</div>
                <div style="font-size: 9px; color: #666;">TROUVÉS</div>
            </td>
            <td style="text-align: center; width: 20%; padding: 12px;">
                <div style="font-size: 24px; font-weight: bold; color: #ef4444;">{{ $stats['total_missing'] }}</div>
                <div style="font-size: 9px; color: #666;">MANQUANTS</div>
            </td>
            <td style="text-align: center; width: 20%; padding: 12px;">
                <div style="font-size: 24px; font-weight: bold; color: #f59e0b;">{{ $stats['total_unexpected'] }}</div>
                <div style="font-size: 9px; color: #666;">INATTENDUS</div>
            </td>
            <td style="text-align: center; width: 20%; padding: 12px;">
                <div style="font-size: 24px; font-weight: bold; color: #3b82f6;">{{ $stats['completion_rate'] }}%</div>
                <div style="font-size: 9px; color: #666;">COMPLÉTION</div>
            </td>
        </tr>
    </table>

    @if(!empty($stats['condition_breakdown']))
        <h2>Répartition par condition</h2>
        <table>
            <thead>
                <tr><th>Condition</th><th>Nombre</th></tr>
            </thead>
            <tbody>
                @foreach($stats['condition_breakdown'] as $condition => $count)
                    <tr><td>{{ $condition }}</td><td>{{ $count }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Liste détaillée des items</h2>
    <table>
        <thead>
            <tr>
                <th>Asset</th>
                <th>Code</th>
                <th>Catégorie</th>
                <th>Statut</th>
                <th>Condition</th>
                <th>Scanné par</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
                <tr>
                    <td>{{ $item->asset?->name ?? '—' }}</td>
                    <td>{{ $item->asset?->asset_code ?? '—' }}</td>
                    <td>{{ $item->asset?->category?->name ?? '—' }}</td>
                    <td>
                        <span class="badge badge-{{ $item->status->value }}">
                            {{ $item->status->getLabel() }}
                        </span>
                    </td>
                    <td>{{ $item->condition?->name ?? '—' }}</td>
                    <td>{{ $item->scanner?->name ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Rapport généré par {{ config('app.name') }} — {{ now()->format('d/m/Y H:i') }}
    </div>
</body>
</html>
