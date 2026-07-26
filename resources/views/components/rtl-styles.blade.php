@props(['area' => 'frontend'])

@php
    $rtlRelativePath = $area.'/css/rtl.css';
    $rtlAbsolutePath = public_path($rtlRelativePath);
@endphp

@if(language_direction() === 'rtl' && file_exists($rtlAbsolutePath))
    <link rel="stylesheet" href="{{ asset($rtlRelativePath.'?v='.filemtime($rtlAbsolutePath)) }}">
@endif
