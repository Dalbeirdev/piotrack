{{--
    A published website page (WEB). Mobile-first and responsive by construction:
    a single fluid column with a max width, fluid type, and grids that collapse
    to one column on small screens — no fixed pixel layout to break.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $page->meta_title ?: $page->title }}</title>
    @if ($page->meta_description)
        <meta name="description" content="{{ $page->meta_description }}">
    @endif
    <style>
        :root { --ink:#111827; --muted:#6b7280; --line:#e5e7eb; --bg:#ffffff; --accent:#1d4ed8; --soft:#f9fafb; }
        @media (prefers-color-scheme: dark) {
            :root { --ink:#f3f4f6; --muted:#9ca3af; --line:#374151; --bg:#0b0f19; --accent:#60a5fa; --soft:#111827; }
        }
        * { box-sizing: border-box; }
        body { margin:0; background:var(--bg); color:var(--ink);
               font:16px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif; }
        .wrap { max-width: 72rem; margin: 0 auto; padding: 1.5rem; }
        header.site { border-bottom:1px solid var(--line); }
        h1 { font-size: clamp(1.9rem, 5vw, 3rem); line-height:1.15; margin:0 0 .5rem; }
        h2 { font-size: clamp(1.3rem, 3vw, 1.9rem); margin:0 0 .75rem; }
        .lead { color:var(--muted); font-size: clamp(1rem, 2.2vw, 1.25rem); margin:0; }
        section { padding: 2.5rem 0; border-bottom:1px solid var(--line); }
        section:last-of-type { border-bottom:0; }
        .grid { display:grid; gap:1rem; grid-template-columns:1fr; }
        @media (min-width: 40rem) { .grid { grid-template-columns: repeat(2, 1fr); } }
        @media (min-width: 64rem) { .grid.three { grid-template-columns: repeat(3, 1fr); } }
        .card { border:1px solid var(--line); border-radius:.75rem; padding:1rem; background:var(--soft); }
        .cta { background:var(--soft); border:1px solid var(--line); border-radius:1rem; padding:1.75rem; text-align:center; }
        .btn { display:inline-block; background:var(--accent); color:#fff; text-decoration:none;
               padding:.8rem 1.4rem; border-radius:.6rem; font-weight:600; }
        footer.site { color:var(--muted); font-size:.875rem; padding:2rem 0; }
    </style>
</head>
<body>
<header class="site">
    <div class="wrap"><strong>{{ $organization->name }}</strong></div>
</header>

<main class="wrap">
    <section>
        <h1>{{ $page->headline ?: $page->title }}</h1>
        @if ($page->subheadline)
            <p class="lead">{{ $page->subheadline }}</p>
        @endif
    </section>

    @foreach ($sections as $section)
        <section>
            @if ($section->heading)
                <h2>{{ $section->heading }}</h2>
            @endif

            @if ($section->type === 'cta' || $section->type === 'offer')
                <div class="cta">
                    @if ($section->body)<p>{{ $section->body }}</p>@endif
                    @if ($page->form_id)
                        <a class="btn" href="{{ url('/f/'.optional($page->form)->slug) }}">Get in touch</a>
                    @endif
                </div>
            @elseif (in_array($section->type, ['logos', 'testimonials', 'reviews', 'case_studies', 'awards', 'trust', 'services'], true))
                <div class="grid {{ in_array($section->type, ['logos', 'services'], true) ? 'three' : '' }}">
                    @forelse (($section->settings['items'] ?? []) as $item)
                        <div class="card">{{ is_array($item) ? ($item['label'] ?? '') : $item }}</div>
                    @empty
                        @if ($section->body)<p>{{ $section->body }}</p>@endif
                    @endforelse
                </div>
            @else
                @if ($section->body)<p>{{ $section->body }}</p>@endif
            @endif
        </section>
    @endforeach
</main>

<footer class="site">
    <div class="wrap">&copy; {{ date('Y') }} {{ $organization->name }}</div>
</footer>
</body>
</html>
