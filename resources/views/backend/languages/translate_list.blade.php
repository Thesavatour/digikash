@foreach($translationRows as $row)
    <tr @class(['trans-row-missing' => $row['missing']])>
        <td><span class="badge rounded-pill bg-light text-body-secondary border badge-group">{{ $row['group'] }}</span></td>
        <td><span class="trans-key" title="{{ $row['key'] }}">{{ Str::limit($row['key'], 40) }}</span></td>
        <td title="{{ $row['source'] }}">{{ Str::limit($row['source'], 45) }}</td>
        <td>
            @if($row['missing'])
                <span class="badge rounded-pill bg-warning text-dark trans-missing">{{ __('Not translated') }}</span>
            @else
                <span title="{{ $row['target'] }}">{{ Str::limit($row['target'], 45) }}</span>
            @endif
        </td>
        <td class="text-end">
            <button type="button" class="editKeyword btn btn-sm btn-outline-primary d-inline-flex align-items-center"
                    data-language="{{ $languageCode }}"
                    data-group="{{ $row['group'] }}" data-key="{{ $row['key'] }}"
                    data-value="{{ $row['target'] }}"
                    title="{{ __('Edit Keyword') }}">
                <x-icon name="edit" class="icon"/><span class="d-none d-md-inline ms-1">{{ __('Edit') }}</span>
            </button>
        </td>
    </tr>
@endforeach
