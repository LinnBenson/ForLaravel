<x-filament-panels::page>
    @php
        $routeData = $this->getRouteData();
    @endphp

    {{-- 路由概览统计 --}}
    <div class="route-information-stats">
        <x-filament::section compact>
            <div class="route-information-stat">
                <x-filament::icon icon="heroicon-o-queue-list" />
                <span><strong>{{ $routeData['total'] }}</strong>路由总数</span>
            </div>
        </x-filament::section>
        <x-filament::section compact>
            <div class="route-information-stat">
                <x-filament::icon icon="heroicon-o-tag" />
                <span><strong>{{ $routeData['named'] }}</strong>命名路由</span>
            </div>
        </x-filament::section>
        <x-filament::section compact>
            <div class="route-information-stat">
                <x-filament::icon icon="heroicon-o-funnel" />
                <span><strong>{{ $routeData['filtered'] }}</strong>当前结果</span>
            </div>
        </x-filament::section>
    </div>

    {{-- 路由搜索与列表 --}}
    <x-filament::section
        heading="系统路由"
        description="展示当前应用已注册的请求方法、URI、名称、处理器和中间件。"
        icon="heroicon-o-map"
    >
        <div class="route-information-toolbar">
            <label class="route-information-search">
                <x-filament::icon icon="heroicon-o-magnifying-glass" />
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="搜索 URI、名称、处理器或中间件"
                >
            </label>
            <x-filament::input.wrapper
                class="route-information-method-filter"
                prefix-icon="heroicon-o-funnel"
                prefix-icon-color="primary"
            >
                <x-filament::input.select wire:model.live="method" aria-label="按请求方法筛选">
                    <option value="all">全部方法</option>
                    @foreach ( $routeData['methods'] as $method )
                        <option value="{{ $method }}">{{ $method }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>

        <div class="route-information-table-wrap">
            <table class="route-information-table">
                <thead>
                    <tr>
                        <th>路由名称</th>
                        <th>方法</th>
                        <th>域名 / URI</th>
                        <th>处理器</th>
                        <th>中间件</th>
                        <th>来源文件</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ( $routeData['routes'] as $index => $route )
                        <tr wire:key="route-{{ $index }}-{{ md5( $route['searchable'] ) }}">
                            <td><code class="route-information-name">{{ $route['name'] }}</code></td>
                            <td>
                                <div class="route-information-methods">
                                    @foreach ( $route['methods'] as $routeMethod )
                                        <span class="route-information-method is-{{ strtolower( $routeMethod ) }}">{{ $routeMethod }}</span>
                                    @endforeach
                                </div>
                            </td>
                            <td>
                                @if ( $route['domain'] !== '-' )
                                    <span class="route-information-domain">{{ $route['domain'] }}</span>
                                @endif
                                <code class="route-information-uri">/{{ ltrim( $route['uri'], '/' ) }}</code>
                            </td>
                            <td>
                                <details class="route-information-action">
                                    <summary>
                                        <code>{{ $route['action_short'] }}</code>
                                        <x-filament::icon icon="heroicon-o-chevron-down" />
                                    </summary>
                                    <div>
                                        <code class="route-information-action-item">{{ $route['action'] }}</code>
                                    </div>
                                </details>
                            </td>
                            <td>
                                @if ( $route['middleware'] === [] )
                                    <span class="route-information-muted">-</span>
                                @else
                                    <details class="route-information-middleware">
                                        <summary>
                                            <span>{{ count( $route['middleware'] ) }} 项</span>
                                            <x-filament::icon icon="heroicon-o-chevron-down" />
                                        </summary>
                                        <div>
                                            @foreach ( $route['middleware'] as $middleware )
                                                <code class="route-information-middleware-item">{{ $middleware }}</code>
                                            @endforeach
                                        </div>
                                    </details>
                                @endif
                            </td>
                            <td>
                                <div class="route-information-source">
                                    <details>
                                        <summary>
                                            <span class="route-information-source-type">{{ $route['source_type'] }}</span>
                                            <x-filament::icon icon="heroicon-o-chevron-down" />
                                        </summary>
                                        @if ( $route['source_file'] === '-' )
                                            <span class="route-information-source-empty">无法定位来源文件</span>
                                        @else
                                            <code class="route-information-source-path">{{ $route['source_file'] }}</code>
                                        @endif
                                    </details>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="route-information-empty">没有找到匹配的路由。</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <style>
        /* 路由页面概览统计 */
        .route-information-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1rem;
        }
        .route-information-stat {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: rgb(75 85 99);
        }
        .route-information-stat svg {
            width: 1.5rem;
            height: 1.5rem;
            color: var(--primary-600);
        }
        .route-information-stat span {
            display: flex;
            flex-direction: column;
            font-size: 0.8125rem;
        }
        .route-information-stat strong {
            color: rgb(17 24 39);
            font-size: 1.5rem;
            line-height: 1.75rem;
        }
        /* 路由搜索与筛选 */
        .route-information-toolbar {
            display: flex;
            margin-bottom: 1rem;
            align-items: center;
            gap: 0.75rem;
        }
        .route-information-search {
            display: flex;
            min-width: 16rem;
            flex: 1;
            align-items: center;
            border: 1px solid rgb(209 213 219);
            border-radius: 0.5rem;
            background: white;
        }
        .route-information-search:focus-within {
            border-color: var(--primary-500);
            box-shadow: 0 0 0 1px var(--primary-500);
        }
        .route-information-search svg {
            width: 1.1rem;
            height: 1.1rem;
            margin-left: 0.75rem;
            color: rgb(107 114 128);
        }
        .route-information-search input {
            min-height: 2.5rem;
            border: 0;
            background: transparent;
            color: rgb(17 24 39);
            outline: none;
        }
        .route-information-search input {
            width: 100%;
            padding: 0 0.75rem;
        }
        .route-information-method-filter {
            width: 13rem;
            flex: none;
        }
        .route-information-method-filter select {
            min-height: 2.5rem;
            font-weight: 600;
        }
        /* 路由数据表格 */
        .route-information-table-wrap {
            overflow-x: auto;
            border: 1px solid rgb(229 231 235);
            border-radius: 0.75rem;
        }
        .route-information-table {
            width: 100%;
            min-width: 90rem;
            border-collapse: collapse;
            font-size: 0.8125rem;
            text-align: left;
        }
        .route-information-table th {
            padding: 0.75rem 1rem;
            background: rgb(249 250 251);
            color: rgb(75 85 99);
            font-weight: 600;
            white-space: nowrap;
        }
        .route-information-table td {
            max-width: 22rem;
            padding: 0.75rem 1rem;
            border-top: 1px solid rgb(229 231 235);
            color: rgb(55 65 81);
            vertical-align: top;
        }
        .route-information-table tbody tr:hover {
            background: color-mix(in srgb, var(--primary-500) 4%, transparent);
        }
        .route-information-table code {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            overflow-wrap: anywhere;
        }
        .route-information-muted {
            color: rgb(156 163 175);
        }
        .route-information-name {
            color: rgb(55 65 81);
            font-weight: 600;
        }
        .route-information-methods {
            display: flex;
            flex-wrap: wrap;
            gap: 0.25rem;
        }
        .route-information-method {
            padding: 0.15rem 0.45rem;
            border-radius: 9999px;
            background: rgb(219 234 254);
            color: rgb(29 78 216);
            font-size: 0.7rem;
            font-weight: 700;
        }
        .route-information-method.is-post { background: rgb(220 252 231); color: rgb(21 128 61); }
        .route-information-method.is-put,
        .route-information-method.is-patch { background: rgb(254 249 195); color: rgb(161 98 7); }
        .route-information-method.is-delete { background: rgb(254 226 226); color: rgb(185 28 28); }
        .route-information-domain {
            display: block;
            margin-bottom: 0.25rem;
            color: rgb(107 114 128);
            font-size: 0.75rem;
        }
        .route-information-uri {
            color: var(--primary-700);
            font-weight: 600;
        }
        .route-information-action summary,
        .route-information-middleware summary,
        .route-information-source summary {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            color: var(--primary-600);
            cursor: pointer;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.8125rem;
            font-weight: 600;
            line-height: 1.25rem;
            list-style: none;
            white-space: nowrap;
        }
        .route-information-action summary::-webkit-details-marker,
        .route-information-middleware summary::-webkit-details-marker,
        .route-information-source summary::-webkit-details-marker {
            display: none;
        }
        .route-information-action summary svg,
        .route-information-middleware summary svg,
        .route-information-source summary svg {
            width: 0.9rem;
            height: 0.9rem;
            flex: none;
            transition: transform 150ms ease;
        }
        .route-information-action[open] summary svg,
        .route-information-middleware[open] summary svg,
        .route-information-source details[open] summary svg {
            transform: rotate(180deg);
        }
        .route-information-action div,
        .route-information-middleware div {
            display: flex;
            margin-top: 0.5rem;
            flex-direction: column;
            align-items: flex-start;
            gap: 0.4rem;
        }
        .route-information-action-item,
        .route-information-middleware-item {
            display: inline-block;
            max-width: 100%;
            padding: 0.3rem 0.5rem;
            border: 1px solid color-mix(in srgb, var(--primary-500) 22%, transparent);
            border-radius: 0.375rem;
            background: color-mix(in srgb, var(--primary-500) 8%, transparent);
            color: rgb(55 65 81);
            font-size: 0.75rem;
        }
        .route-information-source {
            min-width: 10rem;
        }
        .route-information-source-type {
            display: inline-flex;
            padding: 0.15rem 0.45rem;
            border-radius: 9999px;
            background: color-mix(in srgb, var(--primary-500) 10%, transparent);
            color: var(--primary-700);
            font-size: 0.7rem;
            font-weight: 600;
            font-family: inherit;
            line-height: 1.1rem;
        }
        .route-information-source-path {
            display: block;
            max-width: 22rem;
            margin-top: 0.45rem;
            padding: 0.3rem 0.5rem;
            border: 1px solid color-mix(in srgb, var(--primary-500) 22%, transparent);
            border-radius: 0.375rem;
            background: color-mix(in srgb, var(--primary-500) 8%, transparent);
            color: rgb(55 65 81);
            font-size: 0.75rem;
        }
        .route-information-source-empty {
            display: block;
            margin-top: 0.45rem;
            color: rgb(156 163 175);
            font-size: 0.75rem;
            white-space: nowrap;
        }
        .route-information-empty {
            padding: 3rem !important;
            color: rgb(107 114 128) !important;
            text-align: center;
        }
        /* 路由页面深色主题 */
        .dark .route-information-stat { color: rgb(156 163 175); }
        .dark .route-information-stat strong,
        .dark .route-information-search input { color: rgb(243 244 246); }
        .dark .route-information-search { border-color: rgb(75 85 99); background: rgb(17 24 39); }
        .dark .route-information-table-wrap { border-color: rgb(55 65 81); }
        .dark .route-information-table th { background: rgb(31 41 55); color: rgb(209 213 219); }
        .dark .route-information-table td { border-color: rgb(55 65 81); color: rgb(209 213 219); }
        .dark .route-information-name,
        .dark .route-information-action-item,
        .dark .route-information-middleware-item,
        .dark .route-information-source-path { color: rgb(229 231 235); }
        .dark .route-information-source-type { color: var(--primary-300); }
        .dark .route-information-uri { color: var(--primary-400); }
        @media (max-width: 768px) {
            .route-information-stats { grid-template-columns: minmax(0, 1fr); }
            .route-information-toolbar { align-items: stretch; flex-direction: column; }
            .route-information-search { min-width: 0; }
            .route-information-method-filter { width: 100%; }
        }
    </style>
</x-filament-panels::page>
