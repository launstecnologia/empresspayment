@php
    $pageTitle = $pageTitle ?? trim($__env->yieldContent('title'));
    $documentTitle = $pageTitle !== '' ? "{$pageTitle} · {$appName}" : $appName;
    $description = $description ?? (trim($__env->yieldContent('meta_description')) ?: ($metaDescription ?? ''));
    $robots = $robots ?? (trim($__env->yieldContent('meta_robots')) ?: ($metaRobots ?? 'noindex, nofollow'));
    $canonical = $canonical ?? (trim($__env->yieldContent('canonical')) ?: url()->current());
    $themeColor = $themeColor ?? '#2563eb';
    $keywords = $metaKeywords ?? '';

    $faviconHrefSetting = $faviconUrl ?? null;
    $faviconFromUpload = $faviconHrefSetting && str_contains((string) $faviconHrefSetting, '/storage/');
    $favicon32 = $faviconFromUpload ? $faviconHrefSetting : asset('favicon-32.png');
    $favicon16 = $faviconFromUpload ? $faviconHrefSetting : asset('favicon-16.png');
    $faviconBase = $faviconFromUpload ? $faviconHrefSetting : asset('favicon.png');
    $appleTouchIcon = $faviconFromUpload ? $faviconHrefSetting : asset('apple-touch-icon.png');
@endphp
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<title>{{ $documentTitle }}</title>
<meta name="description" content="{{ $description }}">
@if ($keywords !== '')
<meta name="keywords" content="{{ $keywords }}">
@endif
<meta name="author" content="{{ $appName }}">
<meta name="robots" content="{{ $robots }}">
<meta name="application-name" content="{{ $appName }}">
<meta name="theme-color" content="{{ $themeColor }}">
<link rel="canonical" href="{{ $canonical }}">
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="{{ $favicon32 }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ $favicon16 }}">
<link rel="icon" type="image/png" href="{{ $faviconBase }}">
<link rel="shortcut icon" href="{{ $favicon32 }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ $appleTouchIcon }}">
<link rel="manifest" href="{{ asset('site.webmanifest') }}">
<meta property="og:type" content="website">
<meta property="og:locale" content="pt_BR">
<meta property="og:site_name" content="{{ $appName }}">
<meta property="og:title" content="{{ $documentTitle }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:image" content="{{ $faviconBase }}">
<meta name="twitter:card" content="summary">
<meta name="twitter:title" content="{{ $documentTitle }}">
<meta name="twitter:description" content="{{ $description }}">
<meta name="twitter:image" content="{{ $faviconBase }}">
