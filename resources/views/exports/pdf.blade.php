<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #000; }
  h1 { font-size: 14px; margin: 0; text-align: center; }
  h2 { font-size: 12px; margin: 2px 0; text-align: center; }
  .sub { text-align: center; color: #444; margin-bottom: 10px; }
  h3 { font-size: 10px; margin: 12px 0 4px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
  th, td { border: 1px solid #888; padding: 3px 4px; }
  th { background: #e9ecef; }
  .r { text-align: right; }
  .b td { font-weight: bold; }
  .foot { margin-top: 14px; color: #666; font-size: 8px; }
</style>
</head>
<body>
  <h1>{{ \App\Models\Setting::val('company_name') }}</h1>
  <h2>{{ $r['title'] }}</h2>
  <div class="sub">{{ $r['subtitle'] }}</div>

  @foreach($r['tables'] as $t)
    @if(!empty($t['heading']))<h3>{{ $t['heading'] }}</h3>@endif
    <table>
      <thead>
        <tr>
          @foreach($t['columns'] as $c)
            <th class="{{ $c['type'] !== 'text' ? 'r' : '' }}">{{ $c['label'] }}</th>
          @endforeach
        </tr>
      </thead>
      <tbody>
        @foreach($t['rows'] as $row)
          <tr class="{{ $row['bold'] ? 'b' : '' }}">
            @foreach($row['cells'] as $i => $v)
              @php
                $type = $t['columns'][$i]['type'];
                if ($v === null || $v === '') {
                    $text = '';
                } elseif (is_int($v) || is_float($v)) {
                    $text = number_format($v, $type === 'qty' ? 2 : 0, ',', '.');
                } else {
                    $text = $v;
                }
                $pad = ($i === 0 && $row['indent']) ? 'padding-left:' . (4 + $row['indent'] * 12) . 'px' : '';
              @endphp
              <td class="{{ $type !== 'text' ? 'r' : '' }}" style="{{ $pad }}">{{ $text }}</td>
            @endforeach
          </tr>
        @endforeach
      </tbody>
    </table>
  @endforeach

  <div class="foot">Dicetak {{ now()->format('d/m/Y H:i') }} oleh {{ auth()->user()->name }}</div>
</body>
</html>