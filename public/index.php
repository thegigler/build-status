<?php
declare(strict_types=1);

use BuildStatus\App;
use BuildStatus\ConfigLoader;

require dirname(__DIR__) . '/src/bootstrap.php';

$config = ConfigLoader::load(dirname(__DIR__) . '/config/builds.json');
$snapshot = (new App($config))->snapshot();

function h(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function humanDuration(?int $seconds): string {
    if ($seconds === null) return '—';
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    return $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m";
}

function groupStatusStyle(array $items): string {
    $statusColors = [
        'healthy' => '#00af00',
        'running' => '#edf3ff',
        'unknown' => '#eceff2',
        'stale' => '#eceff2',
        'late' => '#fff0f0',
        'very-late' => '#b42318',
        'missing' => '#22272e'
    ];

    $counts = array_fill_keys(array_keys($statusColors), 0);
    foreach ($items as $item) {
        $status = $item['status'] ?? 'unknown';
        $counts[$status] = ($counts[$status] ?? 0) + 1;
    }

    $total = array_sum($counts);
    if ($total === 0) return '';

    $stops = [];
    $position = 0.0;
    foreach ($counts as $status => $count) {
        if ($count === 0) continue;
        $start = $position;
        $position += ($count / $total) * 100;
        $stops[] = $statusColors[$status] . " {$start}% {$position}%";
    }

    return 'background: linear-gradient(90deg, ' . implode(', ', $stops) . ');';
}

function faviconDataUri(array $groups): string {
    $statusColors = [
        'healthy' => '#00af00',
        'running' => '#edf3ff',
        'unknown' => '#eceff2',
        'late' => '#fff0f0',
        'very-late' => '#b42318'
    ];

    $counts = array_fill_keys(array_keys($statusColors), 0);
    foreach ($groups as $group) {
        foreach ($group['items'] as $item) {
            $status = $item['status'] ?? 'unknown';
            if ($status === 'stale') $status = 'unknown';
            if (isset($counts[$status])) $counts[$status]++;
        }
    }

    $total = array_sum($counts);
    $center = 16;
    $radius = 15;
    $paths = [];
    $angle = -M_PI / 2;

    if ($total === 0) {
        $paths[] = '<circle cx="16" cy="16" r="15" fill="#eceff2"/>';
    } elseif ($total === 1) {
        foreach ($counts as $status => $count) {
            if ($count > 0) {
                $paths[] = '<circle cx="16" cy="16" r="15" fill="' . $statusColors[$status] . '"/>';
                break;
            }
        }
    } else {
        foreach ($counts as $status => $count) {
            if ($count === 0) continue;
            $nextAngle = $angle + (2 * M_PI * $count / $total);
            $x1 = $center + $radius * cos($angle);
            $y1 = $center + $radius * sin($angle);
            $x2 = $center + $radius * cos($nextAngle);
            $y2 = $center + $radius * sin($nextAngle);
            $largeArc = ($nextAngle - $angle) > M_PI ? 1 : 0;
            $paths[] = sprintf(
                '<path d="M 16 16 L %.3f %.3f A 15 15 0 %d 1 %.3f %.3f Z" fill="%s"/>',
                $x1,
                $y1,
                $largeArc,
                $x2,
                $y2,
                $statusColors[$status]
            );
            $angle = $nextAngle;
        }
    }

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32">'
        . implode('', $paths)
        . '<circle cx="16" cy="16" r="15" fill="none" stroke="#22272e" stroke-width="1"/>'
        . '</svg>';

    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

$requestedView = isset($_GET['view']) ? (string) $_GET['view'] : null;
$selectedGroupIndex = 0;
if ($requestedView !== null) {
    foreach ($snapshot['groups'] as $index => $group) {
        if ((string) $group['id'] === $requestedView) {
            $selectedGroupIndex = $index;
            break;
        }
    }
}
$selectedGroupId = $snapshot['groups'][$selectedGroupIndex]['id'] ?? null;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="<?= h(faviconDataUri($snapshot['groups'])) ?>" type="image/svg+xml">
  <title>Build Status</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
<main>
<nav class="group-links" aria-label="Build status groups">
  <?php foreach ($snapshot['groups'] as $index => $group): ?>
    <a class="group-link<?= $index === $selectedGroupIndex ? ' active' : '' ?>"
       href="?view=<?= h($group['id']) ?>"
       data-group-id="<?= h($group['id']) ?>"
       aria-controls="group-<?= h($group['id']) ?>"
       aria-selected="<?= $index === $selectedGroupIndex ? 'true' : 'false' ?>">
      <span class="group-label"><?= h($group['label']) ?></span>
      <span class="group-status-bar" style="<?= h(groupStatusStyle($group['items'])) ?>" aria-hidden="true"></span>
    </a>
  <?php endforeach; ?>
</nav>
  <header>
    <div>
      <p class="eyebrow">Build operations</p>
      <h1>Build Status</h1>
    </div>
    <div class="generated">Generated <?= h(
    (new DateTimeImmutable($snapshot['generatedAt']))
        ->format('M j, Y \\a\\t g:i A T')
) ?></div>
  </header>

  <?php foreach ($snapshot['groups'] as $index => $group): ?>
    <section id="group-<?= h($group['id']) ?>" data-group="<?= h($group['id']) ?>"<?= $index !== $selectedGroupIndex ? ' hidden' : '' ?>>
      <h2><?= h($group['label']) ?></h2>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Artifact</th>
              <th>Status</th>
              <th>Last modified</th>
              <th>Expected start</th>
              <th>Previous duration</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($group['items'] as $item): ?>
            <tr>
              <td class="artifact"><?= h($item['path']) ?></td>
              <td><span class="status status-<?= h($item['status']) ?>"><?= h($item['label']) ?></span></td>
              <td><?= h($item['modifiedAt'] ?? '—') ?></td>
              <td><?= h($item['scheduledStart'] ?? '—') ?></td>
              <td><?= h(humanDuration($item['previousDurationSeconds'])) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  <?php endforeach; ?>

  <footer><a href="status.php">Machine-readable status</a></footer>
</main>
<script>
(() => {
  // Refresh the whole page so the server recalculates build status.
  // Normal group links preserve ?view=... and therefore the selected group.
  window.setInterval(() => {
    window.location.reload();
  }, 60 * 1000);
})();
</script>
</body>
</html>
