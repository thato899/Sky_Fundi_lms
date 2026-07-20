<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>@yield('title') · {{ $branding['platform_name'] ?? 'Sky Fundi' }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&display=swap" rel="stylesheet">
<style>
:root{color-scheme:light;--ink:#1b2735;--muted:#5b6b7c;--paper:#f4f7fa;--card:#fff;--line:#dfe6ef;--primary:{{ $branding['primary_colour'] ?? '#175cd3' }};--secondary:{{ $branding['secondary_colour'] ?? '#0e9384' }};--danger:#b42318;--success:#067647;--warn:#b54708;--display:"Fraunces",Georgia,serif;--radius:.9rem;--shadow:0 10px 30px rgba(23,42,71,.08),0 1px 2px rgba(23,42,71,.06)}
*{box-sizing:border-box}
body{margin:0;background:var(--paper);color:var(--ink);font:16px/1.55 system-ui,-apple-system,"Segoe UI",sans-serif}
a{color:var(--primary)}
:focus-visible{outline:3px solid color-mix(in srgb,var(--primary) 45%,transparent);outline-offset:2px}
.wrap{width:min(1120px,calc(100% - 2rem));margin:auto}
.site-header{position:sticky;top:0;z-index:20;background:rgba(255,255,255,.94);backdrop-filter:blur(8px);border-bottom:1px solid var(--line)}
.nav{min-height:66px;display:flex;align-items:center;gap:1.25rem}
.brand{display:flex;align-items:center;gap:.7rem;color:var(--ink);font-family:var(--display);font-weight:700;font-size:1.15rem;text-decoration:none;letter-spacing:-.01em}
.brand img{max-height:36px;max-width:170px}
.nav-links{display:flex;gap:.25rem;flex:1;min-width:0;overflow-x:auto}
.nav-links a{color:var(--muted);text-decoration:none;font-weight:600;font-size:.93rem;padding:.45rem .7rem;border-radius:.55rem;white-space:nowrap}
.nav-links a:hover{color:var(--ink);background:color-mix(in srgb,var(--primary) 7%,transparent)}
.nav-links a.active{color:var(--primary);background:color-mix(in srgb,var(--primary) 10%,transparent)}
.nav-side{display:flex;align-items:center;gap:.7rem;margin-left:auto}
.persona-chip{display:inline-flex;align-items:center;gap:.4rem;background:linear-gradient(120deg,var(--primary),var(--secondary));color:#fff;font-size:.72rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;padding:.4rem .75rem;border-radius:999px}
.button,button{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:.65rem;background:var(--primary);color:#fff;padding:.7rem 1.05rem;font:inherit;font-weight:700;text-decoration:none;cursor:pointer;transition:filter .15s}
.button:hover,button:hover{filter:brightness(1.08)}
.button.secondary{background:#fff;color:var(--primary);border:1px solid var(--line)}
.button.small,button.small{padding:.45rem .8rem;font-size:.88rem}
main{min-height:calc(100vh - 145px)}
.hero{padding:4.5rem 0 3.75rem;background:linear-gradient(135deg,color-mix(in srgb,var(--primary) 9%,#fff) 0%,color-mix(in srgb,var(--secondary) 9%,#fff) 100%)}
.hero-grid,.two-col{display:grid;grid-template-columns:1.2fr .8fr;gap:3rem;align-items:center}
.eyebrow{color:var(--secondary);font-weight:800;letter-spacing:.09em;text-transform:uppercase;font-size:.76rem}
h1{font-family:var(--display);font-size:clamp(2.1rem,4.5vw,3.9rem);line-height:1.04;letter-spacing:-.02em;margin:.65rem 0 1.25rem}
h2{font-family:var(--display);font-size:clamp(1.4rem,3vw,1.95rem);line-height:1.2;letter-spacing:-.01em}
p{color:var(--muted)}
.actions{display:flex;gap:.8rem;flex-wrap:wrap;margin-top:1.5rem}
.panel,.feature{background:var(--card);border:1px solid var(--line);border-radius:var(--radius);padding:1.5rem;box-shadow:var(--shadow)}
.features{padding:4rem 0}
.feature-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem}
.feature h3{margin-top:0}
.form-shell{width:min(480px,calc(100% - 2rem));margin:4rem auto}
.field{margin:1rem 0}
.field label{display:block;font-weight:700;margin-bottom:.35rem}
.field input,.field select,.field textarea{width:100%;padding:.8rem;border:1px solid #b9c5d3;border-radius:.55rem;font:inherit;background:#fff}
.field input:focus,.field select:focus,.field textarea:focus{outline:3px solid color-mix(in srgb,var(--primary) 22%,transparent);border-color:var(--primary)}
.check{display:flex;align-items:center;gap:.5rem}
.check input{width:auto}
.error,.errors{color:var(--danger)}
.errors{background:#fff1f0;border:1px solid #fecdca;border-radius:.6rem;padding:.8rem}
.stack{display:grid;gap:1rem}
.membership{display:flex;justify-content:space-between;gap:1rem;align-items:center}
.meta{font-size:.9rem;color:var(--muted)}
.chip{display:inline-flex;align-items:center;border-radius:999px;padding:.22rem .65rem;font-size:.78rem;font-weight:700;background:color-mix(in srgb,var(--primary) 10%,#fff);color:var(--primary)}
.chip.success{background:#ecfdf3;color:var(--success)}
.chip.warn{background:#fffaeb;color:var(--warn)}
.chip.danger{background:#fef3f2;color:var(--danger)}
.chip.neutral{background:#eef2f6;color:var(--muted)}
.metric-num{font-variant-numeric:tabular-nums;font-family:var(--display);font-weight:700}
footer{border-top:1px solid var(--line);padding:1.5rem 0;color:var(--muted)}
@media(prefers-reduced-motion:reduce){*{transition:none!important;animation:none!important}}
@media(max-width:760px){.hero{padding:3.25rem 0}.hero-grid,.two-col,.feature-grid{grid-template-columns:1fr}.hero-grid{gap:2rem}.membership{align-items:flex-start;flex-direction:column}.nav{flex-wrap:wrap;padding:.55rem 0}.nav-side{margin-left:0}}
</style></head>
<body><header class="site-header"><div class="wrap nav"><a class="brand" href="{{ route('home') }}">@if(!empty($branding['logo_path']))<img src="{{ asset($branding['logo_path']) }}" alt="{{ $branding['platform_name'] ?? 'Sky Fundi' }}">@else<span>{{ $branding['platform_name'] ?? 'Sky Fundi' }}</span>@endif</a>
@auth<nav class="nav-links" aria-label="Primary">@foreach(($navigation['links'] ?? []) as $link)<a href="{{ $link['href'] }}" @if($link['active']) class="active" aria-current="page" @endif>{{ $link['label'] }}</a>@endforeach</nav>
<div class="nav-side">@if(!empty($navigation['persona']))<span class="persona-chip">{{ $navigation['persona'] }}</span>@endif<form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="button secondary small">Log out</button></form></div>
@else<div class="nav-side"><a class="button" href="{{ route('login') }}">Log in</a></div>@endauth</div></header><main>@yield('content')</main><footer><div class="wrap">{{ $branding['platform_name'] ?? 'Sky Fundi' }} · Education platform foundations built for responsible growth.</div></footer></body></html>
