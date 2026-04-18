{{-- Dialog CRUD untuk tabel strategic (bukan log geofence). Query string filter dipertahankan lewat $strategicSuffix. --}}
@php
    $gpStore = route('strategic.group-scores.store').$strategicSuffix;
    $anStore = route('strategic.anomalies.store').$strategicSuffix;
    $utStore = route('strategic.utilization-daily.store').$strategicSuffix;
    $mpStore = route('strategic.maintenance-plans.store').$strategicSuffix;
    $mrStore = route('strategic.maintenance-records.store').$strategicSuffix;
    $gpBase = url('/strategic/group-scores');
    $anBase = url('/strategic/anomalies');
    $utBase = url('/strategic/utilization-daily');
    $mpBase = url('/strategic/maintenance-plans');
    $mrBase = url('/strategic/maintenance-records');
@endphp

<dialog id="strategic-dialog-gp" class="w-[min(96vw,26rem)] max-h-[min(92vh,36rem)] overflow-y-auto rounded-xl border border-slate-200 bg-white p-0 shadow-2xl">
    <form id="strategic-form-gp" method="post" action="{{ $gpStore }}" class="flex flex-col">
        @csrf
        <input type="hidden" name="_method" id="strategic-form-gp-method" value="">
        <div class="border-b border-slate-100 px-4 py-3">
            <h4 id="strategic-dialog-gp-title" class="text-sm font-semibold text-slate-900">Rapor kinerja kelompok</h4>
        </div>
        <div class="space-y-2 px-4 py-3 text-xs">
            <div>
                <label class="font-medium text-slate-700">Kelompok tani</label>
                <select name="group_id" id="strategic-gp-group" required class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm">
                    <option value="">— Pilih —</option>
                    @foreach ($groups as $gr)
                        <option value="{{ $gr->id }}">{{ $gr->name }}@if ($gr->village) — {{ $gr->village }}@endif</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="font-medium text-slate-700">Periode</label>
                <input type="text" name="period" id="strategic-gp-period" required maxlength="32" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5 font-mono text-sm" placeholder="2026-Q2">
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="text-slate-700">Keaktifan</label>
                    <input type="number" step="0.01" min="0" max="100" name="activity_score" id="strategic-gp-act" required class="mt-0.5 w-full rounded border border-slate-200 px-1 py-1 text-sm">
                </div>
                <div>
                    <label class="text-slate-700">Perawatan</label>
                    <input type="number" step="0.01" min="0" max="100" name="maintenance_score" id="strategic-gp-maint" required class="mt-0.5 w-full rounded border border-slate-200 px-1 py-1 text-sm">
                </div>
            </div>
            {{-- Preview total & grade: dihitung otomatis (server & klien). Nilai ini tidak dikirim ke server. --}}
            <div class="grid grid-cols-2 gap-2 rounded-lg border border-slate-200 bg-slate-50/80 p-2">
                <div>
                    <label class="text-slate-600">Total (otomatis)</label>
                    <div id="strategic-gp-total-preview" class="mt-0.5 rounded border border-slate-200 bg-white px-2 py-1 text-sm font-semibold text-slate-800 tabular-nums">—</div>
                </div>
                <div>
                    <label class="text-slate-600">Grade (otomatis)</label>
                    <div id="strategic-gp-grade-preview" class="mt-0.5 rounded border border-slate-200 bg-white px-2 py-1 text-sm font-semibold text-slate-800">—</div>
                </div>
            </div>
            <p class="text-[11px] leading-snug text-slate-500">Total = rata-rata Keaktifan &amp; Perawatan. Grade: A ≥ 90 · B 80–89 · C 70–79 · D 60–69 · E &lt; 60.</p>
            <div>
                <label class="font-medium text-slate-700">Catatan</label>
                <textarea name="notes" id="strategic-gp-notes" rows="2" maxlength="2000" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm"></textarea>
            </div>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50/80 px-4 py-3">
            <button type="button" class="strategic-dialog-cancel rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700">Batal</button>
            <button type="button" class="strategic-form-submit rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700">Simpan</button>
        </div>
    </form>
</dialog>

<dialog id="strategic-dialog-an" class="w-[min(96vw,28rem)] max-h-[min(92vh,40rem)] overflow-y-auto rounded-xl border border-slate-200 bg-white p-0 shadow-2xl">
    <form id="strategic-form-an" method="post" action="{{ $anStore }}" class="flex flex-col">
        @csrf
        <input type="hidden" name="_method" id="strategic-form-an-method" value="">
        <div class="border-b border-slate-100 px-4 py-3">
            <h4 id="strategic-dialog-an-title" class="text-sm font-semibold text-slate-900">Anomali</h4>
        </div>
        <div class="space-y-2 px-4 py-3 text-xs">
            <div>
                <label class="font-medium text-slate-700">Waktu terdeteksi</label>
                <input type="datetime-local" name="detected_at" id="strategic-an-detected" required class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm">
            </div>
            <div>
                <label class="font-medium text-slate-700">Alat</label>
                <select name="tractor_id" id="strategic-an-tractor" required class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5 font-mono text-sm">
                    @foreach ($tractors as $tr)
                        <option value="{{ $tr->id }}">{{ $tr->id }}@if ($tr->name) — {{ $tr->name }}@endif</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="font-medium text-slate-700">Tipe</label>
                <input type="text" name="anomaly_type" id="strategic-an-type" required maxlength="120" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm">
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="font-medium text-slate-700">Severity</label>
                    <select name="severity" id="strategic-an-sev" required class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm">
                        <option value="HIGH">HIGH</option>
                        <option value="MEDIUM">MEDIUM</option>
                        <option value="LOW">LOW</option>
                    </select>
                </div>
                <div>
                    <label class="font-medium text-slate-700">Status</label>
                    <select name="status" id="strategic-an-status" required class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm">
                        <option value="OPEN">OPEN</option>
                        <option value="RESOLVED">RESOLVED</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="font-medium text-slate-700">Deskripsi</label>
                <textarea name="description" id="strategic-an-desc" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm"></textarea>
            </div>
            <div>
                <label class="font-medium text-slate-700">Selesai (opsional)</label>
                <input type="datetime-local" name="resolved_at" id="strategic-an-resolved" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm">
            </div>
            <div>
                <label class="font-medium text-slate-700">Catatan penyelesaian</label>
                <textarea name="resolved_note" id="strategic-an-res-note" rows="2" maxlength="2000" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm"></textarea>
            </div>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50/80 px-4 py-3">
            <button type="button" class="strategic-dialog-cancel rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700">Batal</button>
            <button type="button" class="strategic-form-submit rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700">Simpan</button>
        </div>
    </form>
</dialog>

<dialog id="strategic-dialog-ut" class="w-[min(96vw,26rem)] max-h-[min(92vh,36rem)] overflow-y-auto rounded-xl border border-slate-200 bg-white p-0 shadow-2xl">
    <form id="strategic-form-ut" method="post" action="{{ $utStore }}" class="flex flex-col">
        @csrf
        <input type="hidden" name="_method" id="strategic-form-ut-method" value="">
        <div class="border-b border-slate-100 px-4 py-3">
            <h4 id="strategic-dialog-ut-title" class="text-sm font-semibold text-slate-900">Utilisasi harian</h4>
        </div>
        <div class="space-y-2 px-4 py-3 text-xs">
            <div>
                <label class="font-medium text-slate-700">Alat</label>
                <select name="tractor_id" id="strategic-ut-tractor" required class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5 font-mono text-sm">
                    @foreach ($tractors as $tr)
                        <option value="{{ $tr->id }}">{{ $tr->id }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="font-medium text-slate-700">Tanggal</label>
                <input type="date" name="date" id="strategic-ut-date" required class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm">
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div><label class="text-slate-700">Hari aktif</label><input type="number" name="active_days_rolling" id="strategic-ut-days" required min="0" class="mt-0.5 w-full rounded border border-slate-200 px-1 py-1 text-sm"></div>
                <div><label class="text-slate-700">Est. jam</label><input type="number" step="0.1" name="estimated_hours" id="strategic-ut-hours" required min="0" class="mt-0.5 w-full rounded border border-slate-200 px-1 py-1 text-sm"></div>
            </div>
            <div>
                <label class="font-medium text-slate-700">Utilisasi %</label>
                <input type="number" step="0.1" name="utilization_pct" id="strategic-ut-pct" required min="0" max="100" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm">
            </div>
            <div>
                <label class="font-medium text-slate-700">Status</label>
                <input type="text" name="utilization_status" id="strategic-ut-st" maxlength="32" placeholder="TINGGI / SEDANG / …" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm">
            </div>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50/80 px-4 py-3">
            <button type="button" class="strategic-dialog-cancel rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700">Batal</button>
            <button type="button" class="strategic-form-submit rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700">Simpan</button>
        </div>
    </form>
</dialog>

<dialog id="strategic-dialog-mp" class="w-[min(96vw,26rem)] max-h-[min(92vh,36rem)] overflow-y-auto rounded-xl border border-slate-200 bg-white p-0 shadow-2xl">
    <form id="strategic-form-mp" method="post" action="{{ $mpStore }}" class="flex flex-col">
        @csrf
        <input type="hidden" name="_method" id="strategic-form-mp-method" value="">
        <div class="border-b border-slate-100 px-4 py-3">
            <h4 id="strategic-dialog-mp-title" class="text-sm font-semibold text-slate-900">Rencana maintenance</h4>
        </div>
        <div class="space-y-2 px-4 py-3 text-xs">
            <div>
                <label class="font-medium text-slate-700">Alat</label>
                <select name="tractor_id" id="strategic-mp-tractor" required class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5 font-mono text-sm">
                    @foreach ($tractors as $tr)
                        <option value="{{ $tr->id }}">{{ $tr->id }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="font-medium text-slate-700">Tipe tugas</label>
                <input type="text" name="task_type" id="strategic-mp-task" required maxlength="120" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm">
            </div>
            <div class="grid grid-cols-3 gap-2">
                <div><label class="text-slate-700">Interval jam</label><input type="number" step="0.1" name="interval_hours" id="strategic-mp-int" class="mt-0.5 w-full rounded border border-slate-200 px-1 py-1 text-sm"></div>
                <div><label class="text-slate-700">Saat ini</label><input type="number" step="0.1" name="current_hours" id="strategic-mp-cur" class="mt-0.5 w-full rounded border border-slate-200 px-1 py-1 text-sm"></div>
                <div><label class="text-slate-700">Target</label><input type="number" step="0.1" name="due_hours" id="strategic-mp-due" class="mt-0.5 w-full rounded border border-slate-200 px-1 py-1 text-sm"></div>
            </div>
            <div>
                <label class="font-medium text-slate-700">Status</label>
                <select name="status" id="strategic-mp-st" required class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm">
                    <option value="PENDING">PENDING</option>
                    <option value="DONE">DONE</option>
                    <option value="OVERDUE">OVERDUE</option>
                </select>
            </div>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50/80 px-4 py-3">
            <button type="button" class="strategic-dialog-cancel rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700">Batal</button>
            <button type="button" class="strategic-form-submit rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700">Simpan</button>
        </div>
    </form>
</dialog>

<dialog id="strategic-dialog-mr" class="w-[min(96vw,28rem)] max-h-[min(92vh,40rem)] overflow-y-auto rounded-xl border border-slate-200 bg-white p-0 shadow-2xl">
    <form id="strategic-form-mr" method="post" action="{{ $mrStore }}" class="flex flex-col">
        @csrf
        <input type="hidden" name="_method" id="strategic-form-mr-method" value="">
        <div class="border-b border-slate-100 px-4 py-3">
            <h4 id="strategic-dialog-mr-title" class="text-sm font-semibold text-slate-900">Riwayat kesehatan alat</h4>
        </div>
        <div class="space-y-2 px-4 py-3 text-xs">
            <div>
                <label class="font-medium text-slate-700">Alat</label>
                <select name="tractor_id" id="strategic-mr-tractor" required class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5 font-mono text-sm">
                    @foreach ($tractors as $tr)
                        <option value="{{ $tr->id }}">{{ $tr->id }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="font-medium text-slate-700">Tanggal</label>
                <input type="date" name="record_date" id="strategic-mr-date" required class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm">
            </div>
            <div>
                <label class="font-medium text-slate-700">Tipe</label>
                <input type="text" name="record_type" id="strategic-mr-type" required maxlength="64" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm" placeholder="perbaikan / penggantian / …">
            </div>
            <div>
                <label class="font-medium text-slate-700">Deskripsi</label>
                <textarea name="description" id="strategic-mr-desc" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm"></textarea>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="font-medium text-slate-700">Biaya (Rp)</label>
                    <input type="number" step="0.01" name="cost" id="strategic-mr-cost" required min="0" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm">
                </div>
                <div>
                    <label class="font-medium text-slate-700">Teknisi</label>
                    <input type="text" name="technician" id="strategic-mr-tech" maxlength="120" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm">
                </div>
            </div>
            <div>
                <label class="font-medium text-slate-700">Bengkel / lokasi</label>
                <input type="text" name="workshop" id="strategic-mr-workshop" maxlength="120" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm">
            </div>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50/80 px-4 py-3">
            <button type="button" class="strategic-dialog-cancel rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700">Batal</button>
            <button type="button" class="strategic-form-submit rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700">Simpan</button>
        </div>
    </form>
</dialog>
