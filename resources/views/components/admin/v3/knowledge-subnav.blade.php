@props([
    'active' => '',
])

@php
    $admin = auth('admin')->user();
    $canUseUrlImport = $admin instanceof \App\Models\Admin && $admin->canManageProtectedWorkflows();
    $items = [
        [
            'key' => 'knowledge-bases',
            'label' => __('admin.materials.knowledge_bases'),
            'route' => 'admin.knowledge-bases.index',
            'active' => $active === 'knowledge-bases',
        ],
        [
            'key' => 'enterprise',
            'label' => __('admin.materials.knowledge_hub_enterprise'),
            'route' => 'admin.enterprise-knowledge.index',
            'active' => $active === 'enterprise',
        ],
    ];

    if ($canUseUrlImport) {
        $items[] = [
            'key' => 'url-import',
            'label' => __('admin.materials.url_import'),
            'route' => 'admin.url-import',
            'active' => $active === 'url-import',
        ];
    }
@endphp

<x-admin.v3.section-subnav
    :items="$items"
    :label="__('admin.nav.knowledge')"
    name="knowledge"
    embedded
/>
