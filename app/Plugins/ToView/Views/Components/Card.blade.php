@props([
    'title' => null,
    'icon' => 'bi-box',
    'padding' => '24px',
    'open' => 'true',
])

@php $rid = md5( uuid() ); @endphp
<section {{$attributes
    ->merge([
        'data-rid' => $rid,
        'style' => '--padding: '.$padding.';'
    ])
->class(['toview-card sizing '.( in_array( $open, [ 'true', 'off' ] )? 'active' : '' )])}}>
    @if( $title !== null || in_array( $open, [ 'true', 'false' ] ) )
        <div class="toview-card-title">
            <div class="toview-card-title-left">
                <i class="{{$icon}} block bold p4 right8"></i>
                {{$title}}
            </div>
            <div class="toview-card-title-right">
                @if( $open !== 'off' )
                    <i
                        class="bi-chevron-{{$open === 'true' ? 'up' : 'down'}} block bold p4"
                        onclick="$('[data-rid={{$rid}}]').toggleClass('active');$(this).toggleClass('bi-chevron-up bi-chevron-down');"
                    ></i>
                @endif
            </div>
        </div>
    @endif
    <div class="toview-card-content-grid">
    <div class="toview-card-content-wrap">
        <div class="toview-card-content sizing">
            {{ $slot }}
        </div>
    </div>
    </div>
</section>