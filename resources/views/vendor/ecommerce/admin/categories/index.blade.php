@extends('ecommerce::admin.layouts.app')

@section('title', 'Categories')
@section('heading', 'Categories')

{{-- Overrides the package's flat list: top-level categories with their subcategories nested beneath (see AppServiceProvider). --}}
@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <p class="text-sm text-slate-500">
        {{ $categoryTree->count() }} {{ Str::plural('category', $categoryTree->count()) }}
        · {{ $categoryTree->sum(fn ($category) => $category->children->count()) }} subcategories
    </p>
    <a href="{{ route('admin.categories.create') }}" class="rounded-lg bg-teal-700 hover:bg-teal-600 text-white px-4 py-2 text-sm">Add category</a>
</div>

@php
    $statusBadge = fn (bool $isActive): string => $isActive
        ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
        : 'bg-slate-100 text-slate-500 ring-slate-200';
@endphp

<div class="rounded-xl bg-white border border-slate-200 overflow-hidden">
    <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-slate-500 text-left">
        <tr>
            <th class="px-5 py-3 font-medium">Name</th>
            <th class="px-5 py-3 font-medium">Products</th>
            <th class="px-5 py-3 font-medium">Sort</th>
            <th class="px-5 py-3 font-medium">Status</th>
            <th class="px-5 py-3 font-medium"></th>
        </tr>
        </thead>
        @forelse($categoryTree as $category)
            <tbody class="border-t border-slate-200 first-of-type:border-t-0">
            <tr class="bg-slate-50/70">
                <td class="px-5 py-3">
                    <div class="flex items-center gap-2">
                        <span class="font-semibold text-slate-900">{{ $category->name }}</span>
                        @if($category->children->isNotEmpty())
                            <span class="rounded-full bg-teal-50 text-teal-700 ring-1 ring-teal-200 px-2 py-0.5 text-xs">
                                {{ $category->children->count() }} {{ Str::plural('subcategory', $category->children->count()) }}
                            </span>
                        @endif
                    </div>
                </td>
                <td class="px-5 py-3">
                    {{ $category->products_count + $category->children->sum('products_count') }}
                    @if($category->children->isNotEmpty() && $category->products_count > 0)
                        <span class="text-xs text-slate-400">({{ $category->products_count }} direct)</span>
                    @endif
                </td>
                <td class="px-5 py-3">{{ $category->sort_order }}</td>
                <td class="px-5 py-3">
                    <span class="rounded-full ring-1 px-2 py-0.5 text-xs {{ $statusBadge($category->is_active) }}">{{ $category->is_active ? 'Active' : 'Inactive' }}</span>
                </td>
                <td class="px-5 py-3 text-right whitespace-nowrap">
                    <a href="{{ route('admin.categories.edit', $category) }}" class="text-teal-700 hover:underline mr-3">Edit</a>
                    <button type="button" data-delete="{{ route('admin.categories.destroy', $category) }}" class="text-rose-600 hover:underline">Delete</button>
                </td>
            </tr>
            @foreach($category->children as $subcategory)
                <tr class="border-t border-slate-100">
                    <td class="py-2.5 pr-5 pl-8">
                        <div class="flex items-center gap-2">
                            <span aria-hidden="true" class="text-slate-300 font-mono">{{ $loop->last ? '└' : '├' }}─</span>
                            <div>
                                <div class="font-medium text-slate-700">{{ $subcategory->name }}</div>
                                <div class="text-xs text-slate-400 font-mono">{{ $subcategory->slug }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-2.5 {{ $subcategory->products_count === 0 ? 'text-slate-400' : '' }}">{{ $subcategory->products_count }}</td>
                    <td class="px-5 py-2.5 text-slate-500">{{ $subcategory->sort_order }}</td>
                    <td class="px-5 py-2.5">
                        <span class="rounded-full ring-1 px-2 py-0.5 text-xs {{ $statusBadge($subcategory->is_active) }}">{{ $subcategory->is_active ? 'Active' : 'Inactive' }}</span>
                    </td>
                    <td class="px-5 py-2.5 text-right whitespace-nowrap">
                        <a href="{{ route('admin.categories.edit', $subcategory) }}" class="text-teal-700 hover:underline mr-3">Edit</a>
                        <button type="button" data-delete="{{ route('admin.categories.destroy', $subcategory) }}" class="text-rose-600 hover:underline">Delete</button>
                    </td>
                </tr>
            @endforeach
            </tbody>
        @empty
            <tbody>
            <tr><td colspan="5" class="px-5 py-8 text-center text-slate-500">No categories yet.</td></tr>
            </tbody>
        @endforelse
    </table>
</div>
@endsection
