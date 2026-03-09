<div class="w-full grid grid-cols-1 lg:grid-cols-5 gap-6">
    <section class="lg:col-span-4 space-y-4">
        <!-- Webcam preview placeholder -->
        <div class="bg-slate-200 rounded-lg aspect-video flex items-center justify-center text-slate-500">
            <span>Webcam preview</span>
        </div>

        <!-- Stickers placeholder -->
        <div class="bg-slate-100 rounded-lg border border-slate-200 p-4">
            <p class="text-sm font-medium text-slate-600 mb-2">Stickers</p>
            <div class="flex flex-wrap gap-2">
                <span class="inline-block w-10 h-10 rounded bg-slate-300"></span>
                <span class="inline-block w-10 h-10 rounded bg-slate-300"></span>
                <span class="inline-block w-10 h-10 rounded bg-slate-300"></span>
            </div>
        </div>

        <!-- Capture and upload -->
        <div class="flex flex-col sm:flex-row gap-3">
            <button type="button" class="rounded bg-slate-800 px-4 py-2 text-white font-medium hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2">
                Capture
            </button>
            <label class="rounded border border-slate-300 bg-white px-4 py-2 text-slate-700 font-medium hover:bg-slate-50 cursor-pointer text-center">
                <input type="file" accept="image/*" class="sr-only">
                Upload image
            </label>
        </div>
    </section>

    <aside class="lg:col-span-1">
        <div class="bg-white border border-slate-200 rounded-lg p-4 sticky top-4">
            <p class="text-sm font-medium text-slate-600 mb-3">Previous images</p>
            <div class="grid grid-cols-2 lg:grid-cols-1 gap-2">
                <div class="aspect-square bg-slate-200 rounded"></div>
                <div class="aspect-square bg-slate-200 rounded"></div>
                <div class="aspect-square bg-slate-200 rounded"></div>
            </div>
        </div>
    </aside>
</div>
