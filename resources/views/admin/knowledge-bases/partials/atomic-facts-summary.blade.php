<section id="atomic-facts" class="mt-6 rounded-xl border border-(gray)-200 bg-white p-5 shadow-sm sm:p-6">
    <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-gray-100 text-gray-600"><i data-lucide="list-checks" class="h-5 w-5"></i></span>
                <div><h2 class="text-lg font-semibold text-(gray)-950">原子事实</h2><p class="text-sm text-(gray)-500">标准答案、证据与质检版本统一管理</p></div>
            </div>
            <div class="mt-4 flex flex-wrap gap-2 text-xs font-semibold">
                <span class="rounded-full bg-(gray)-100 px-3 py-1.5 text-(gray)-700">{{ (int) data_get($factSummary, 'fact_count', 0) }} 条事实</span>
                <span class="rounded-full bg-amber-50 px-3 py-1.5 text-amber-800">{{ (int) data_get($factSummary, 'pending_count', 0) }} 项待审核</span>
                <span class="rounded-full bg-rose-50 px-3 py-1.5 text-rose-800">{{ (int) data_get($factSummary, 'conflict_count', 0) }} 项冲突</span>
                <span class="rounded-full {{ data_get($factSummary, 'ready') ? 'bg-emerald-50 text-emerald-800' : 'bg-(gray)-100 text-(gray)-700' }} px-3 py-1.5">{{ data_get($factSummary, 'ready') ? '已用于质检' : '尚未发布' }}</span>
            </div>
        </div>
        <a href="{{ route('admin.knowledge-bases.facts.index', $knowledgeBase->id) }}" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white hover:bg-blue-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2 active:scale-[.98]">
            进入原子事实工作台 <i data-lucide="arrow-right" class="h-4 w-4"></i>
        </a>
    </div>
</section>
