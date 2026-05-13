@php
    // --- LOGIKA MENGHITUNG JUMLAH PEKERJAAN TERTUNDA ---
    $pendingCount = 0;
    $targetFilter = '';

    if(Auth::check() && isset($currentUser)) {
        $myRole = $currentUser->role ?? '';
        $myDept = $currentUser->department ?? '';
        $myUsername = $currentUser->username ?? '';
        $isWhAdmin = in_array($myRole, ['Administrator', 'Warehouse']) || ($myRole === 'TeamLeader' && strtolower($myDept) === 'warehouse');

        try {
            if ($myRole === 'Administrator') {
                $c1 = \App\Models\GisRequest::where('status', 'Pending Head')->count();
                $c2 = \App\Models\GisRequest::where('status', 'Pending Plant Head')->count();
                $c3 = \App\Models\GisRequest::where('status', 'Pending Warehouse')->count();
                $c4 = \App\Models\GisRequest::where('status', 'Pending No GI (ERP)')->count();

                $pendingCount = $c1 + $c2 + $c3 + $c4;
                if ($c1 > 0 || $c2 > 0) $targetFilter = 'Pending Head';
                elseif ($c3 > 0) $targetFilter = 'Pending Warehouse';
                elseif ($c4 > 0) $targetFilter = 'Completed';

            } elseif ($isWhAdmin) {
                $c3 = \App\Models\GisRequest::where('status', 'Pending Warehouse')->count();
                $c4 = \App\Models\GisRequest::where('status', 'Pending No GI (ERP)')->count();

                $pendingCount = $c3 + $c4;
                if ($c3 > 0) $targetFilter = 'Pending Warehouse';
                elseif ($c4 > 0) $targetFilter = 'Completed';

            } elseif ($myRole === 'PlantHead') {
                $pendingCount = \App\Models\GisRequest::where('status', 'Pending Plant Head')->count();
                if ($pendingCount > 0) $targetFilter = 'Pending Head';

            } elseif (in_array($myRole, ['SectionHead', 'TeamLeader'])) {
                $pendingCount = \App\Models\GisRequest::where('department', $myDept)->where('status', 'Pending Head')->count();
                if ($pendingCount > 0) $targetFilter = 'Pending Head';

            } else {
                $pendingCount = \App\Models\GisRequest::where('username', $myUsername)->where('status', 'Pending Receive')->count();
                if ($pendingCount > 0) $targetFilter = 'Pending Receive';
            }
        } catch(\Exception $e) {
            $pendingCount = 0;
            $targetFilter = '';
        }
    }
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>GIS Portal - PT Cemindo Gemilang</title>

  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link rel="icon" type="image/png" href="https://i.ibb.co.com/prMYS06h/LOGO-2025-03.png">

  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.29/jspdf.plugin.autotable.min.js"></script>

  <style>
    body { font-family: 'Inter', sans-serif; background-color: #f4f7fe; overflow-x: hidden; }
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    .custom-scroll::-webkit-scrollbar { width: 4px; height: 4px; }
    .custom-scroll::-webkit-scrollbar-track { background: #f1f5f9; }
    .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }

    .loader-spin { border: 3px solid #e2e8f0; border-top: 3px solid #ef4444; border-radius: 50%; width: 18px; height: 18px; animation: spin 0.8s linear infinite; display: inline-block; vertical-align: middle; }
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    .animate-slide-up { animation: slideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
    @keyframes slideUp { from { transform: translateY(15px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

    .shine-effect { position: relative; overflow: hidden; }
    .shine-effect::before { content: ''; position: absolute; top: 0; left: -100%; width: 50%; height: 100%; background: linear-gradient(to right, rgba(255,255,255,0) 0%, rgba(255,255,255,0.3) 50%, rgba(255,255,255,0) 100%); transform: skewX(-20deg); animation: shine 5s infinite; z-index: 1; }
    @keyframes shine { 0% { left: -100%; } 20% { left: 200%; } 100% { left: 200%; } }

    @keyframes gradientBG { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
    .bg-red-live-gradient { background: linear-gradient(-45deg, #ef4444, #dc2626, #991b1b, #ef4444); background-size: 400% 400%; animation: gradientBG 5s ease infinite; }

    .scrolling-text-container { overflow: hidden; white-space: nowrap; position: relative; width: 100%; display: flex; align-items: center; }
    .scrolling-text { display: inline-block; padding-left: 100%; animation: scrollText linear infinite; }
    .scrolling-text:hover { animation-play-state: paused; }
    @keyframes scrollText { 0% { transform: translate(0, 0); } 100% { transform: translate(-100%, 0); } }

    @keyframes ring { 0% { transform: rotate(0); } 10%, 30%, 50%, 70%, 90% { transform: rotate(-15deg); } 20%, 40%, 60%, 80% { transform: rotate(15deg); } 100% { transform: rotate(0); } }
    .animate-ring { animation: ring 2s ease-in-out infinite; transform-origin: top center; }

    .status-badge { padding: 4px 10px; border-radius: 9999px; font-weight: 800; font-size: 0.6rem; text-transform: uppercase; letter-spacing: 0.05em; display: inline-flex; align-items: center; justify-content: center; width: 100%;}
    .btn-animated { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); position: relative; overflow: hidden; }
    .btn-animated:hover { transform: translateY(-2px); box-shadow: 0 8px 15px -3px rgba(0, 0, 0, 0.1); }
    .btn-animated:active { transform: translateY(1px) scale(0.96); }
  </style>
</head>
<body class="text-slate-800 flex h-screen overflow-hidden bg-[#f4f7fe]">

  <div id="sidebar-overlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-40 hidden lg:hidden transition-opacity opacity-0 duration-300" onclick="toggleSidebar()"></div>

  <aside id="sidebar" class="fixed inset-y-0 left-0 w-[260px] bg-white flex flex-col shadow-2xl lg:shadow-[4px_0_24px_rgba(0,0,0,0.02)] z-50 border-r border-slate-100 transform -translate-x-full transition-transform duration-300 ease-in-out lg:translate-x-0 lg:static lg:inset-auto h-full flex-shrink-0">
      <div class="h-20 flex items-center justify-between px-6 border-b border-slate-50">
          <div class="flex items-center">
              <div class="bg-white p-1 rounded-lg shadow-sm mr-2 flex-shrink-0 border border-slate-100">
                  <img src="https://i.ibb.co.com/prMYS06h/LOGO-2025-03.png" alt="Logo Semen Merah Putih" class="h-6 w-auto object-contain">
              </div>
              <span class="font-black text-lg text-slate-800 tracking-tight truncate">GIS <span class="bg-clip-text text-transparent bg-gradient-to-r from-red-500 to-red-700">System</span></span>
          </div>
          <button onclick="toggleSidebar()" class="lg:hidden text-slate-400 hover:text-red-600 text-2xl px-2"><i class="fas fa-times"></i></button>
      </div>

      <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto no-scrollbar">
          <p class="px-3 text-[9px] font-black text-slate-400 uppercase tracking-widest mb-3">Menu Utama</p>
          <a href="{{ url('/') }}" onclick="if(window.innerWidth < 1024) toggleSidebar()" class="flex items-center gap-3 px-4 py-3 rounded-2xl text-sm font-bold transition-all {{ request()->is('/') ? 'bg-gradient-to-r from-red-500 to-red-600 text-white shadow-lg shadow-red-200/50 translate-x-1' : 'text-slate-500 hover:bg-red-50 hover:text-red-600' }}">
              <i class="fas fa-chart-pie w-5 text-center {{ request()->is('/') ? 'text-white' : 'text-slate-400' }}"></i> Dashboard
          </a>
          @if($isAdmin || in_array('gr_submit', $rights))
          <a href="{{ url('/gr') }}" onclick="if(window.innerWidth < 1024) toggleSidebar()" class="flex items-center gap-3 px-4 py-3 rounded-2xl text-sm font-bold transition-all {{ request()->is('gr') ? 'bg-gradient-to-r from-red-500 to-red-600 text-white shadow-lg shadow-red-200/50 translate-x-1' : 'text-slate-500 hover:bg-red-50 hover:text-red-600' }}">
              <i class="fas fa-file-import w-5 text-center {{ request()->is('gr') ? 'text-white' : 'text-slate-400' }}"></i> Good Receive
          </a>
          @endif
          <a href="{{ url('/gi') }}" onclick="if(window.innerWidth < 1024) toggleSidebar()" class="flex items-center gap-3 px-4 py-3 rounded-2xl text-sm font-bold transition-all {{ request()->is('gi') ? 'bg-gradient-to-r from-red-500 to-red-600 text-white shadow-lg shadow-red-200/50 translate-x-1' : 'text-slate-500 hover:bg-red-50 hover:text-red-600' }}">
              <i class="fas fa-file-export w-5 text-center {{ request()->is('gi') ? 'text-white' : 'text-slate-400' }}"></i> Good Issue
          </a>
          <a href="{{ url('/inventory') }}" onclick="if(window.innerWidth < 1024) toggleSidebar()" class="flex items-center gap-3 px-4 py-3 rounded-2xl text-sm font-bold transition-all {{ request()->is('inventory') ? 'bg-gradient-to-r from-red-500 to-red-600 text-white shadow-lg shadow-red-200/50 translate-x-1' : 'text-slate-500 hover:bg-red-50 hover:text-red-600' }}">
              <i class="fas fa-warehouse w-5 text-center {{ request()->is('inventory') ? 'text-white' : 'text-slate-400' }}"></i> Inventory
          </a>
      </nav>

      @if($isAdmin || in_array('export_data', $rights))
      <div class="p-5">
          <div class="bg-red-live-gradient rounded-3xl p-5 text-center text-white shadow-lg shadow-red-200/60 relative overflow-hidden">
              <div class="absolute -right-4 -top-4 w-12 h-12 bg-white/20 rounded-full blur-xl"></div>
              <div class="w-8 h-8 bg-white/20 rounded-xl flex items-center justify-center mx-auto mb-2 backdrop-blur-sm">
                  <i class="fas fa-file-pdf text-sm"></i>
              </div>
              <h4 class="font-black text-xs mb-1">GIS Reports</h4>
              <p class="text-[9px] font-medium opacity-90 mb-3 leading-tight">Dapatkan semua histori transaksi.</p>
              <button onclick="openExportModal(); if(window.innerWidth < 1024) toggleSidebar();" class="w-full bg-white text-red-600 py-2 rounded-xl font-black text-[10px] hover:bg-slate-50 transition shadow-sm btn-animated">Export Data</button>
          </div>
      </div>
      @endif
  </aside>

  <div class="flex-1 flex flex-col h-screen overflow-hidden relative w-full">
      <header class="h-16 sm:h-20 bg-[#f4f7fe]/90 backdrop-blur-xl flex items-center justify-between px-4 sm:px-8 z-30 sticky top-0 border-b border-transparent">
          <div class="flex items-center gap-3">
              <button onclick="toggleSidebar()" class="lg:hidden w-10 h-10 bg-white rounded-xl shadow-sm text-slate-500 hover:text-red-600 text-lg flex items-center justify-center btn-animated">
                  <i class="fas fa-bars"></i>
              </button>
              <h2 class="text-xl sm:text-2xl font-black text-slate-800 tracking-tight hidden sm:block">
                  @if(request()->is('/')) Dashboard
                  @elseif(request()->is('gr')) Good Receive
                  @elseif(request()->is('gi')) Good Issue
                  @elseif(request()->is('inventory')) Inventory
                  @endif
              </h2>
          </div>

          <div class="flex-1"></div>

          <div class="flex items-center gap-2 sm:gap-4">
              <button onclick="toggleLanguage()" class="flex items-center gap-1.5 text-slate-600 hover:text-red-600 transition font-bold text-[10px] sm:text-xs">
                  <span id="lang-label" class="hidden sm:inline">EN (US)</span><span class="sm:hidden">EN</span> <i class="fas fa-chevron-down text-[8px] opacity-50"></i>
              </button>

              <div class="relative">
                  <button onclick="toggleNotifDropdown()" class="relative w-8 h-8 sm:w-10 sm:h-10 bg-white rounded-full flex items-center justify-center shadow-sm transition btn-animated {{ $pendingCount > 0 ? 'text-red-500 hover:text-red-700' : 'text-slate-500 hover:text-red-600' }}" title="Notifications">
                      <i class="far fa-bell {{ $pendingCount > 0 ? 'animate-ring' : '' }}"></i>
                      @if($pendingCount > 0)
                      <span class="absolute top-0 right-0 sm:top-0.5 sm:right-0.5 flex h-3 w-3 sm:h-4 sm:w-4 items-center justify-center">
                          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                          <span class="relative inline-flex rounded-full h-3 w-3 sm:h-4 sm:w-4 bg-red-600 text-white text-[8px] sm:text-[9px] font-bold items-center justify-center border-2 border-white">{{ $pendingCount > 9 ? '9+' : $pendingCount }}</span>
                      </span>
                      @endif
                  </button>
                  <div id="notif-dropdown" class="hidden absolute right-0 mt-3 w-64 sm:w-72 bg-white rounded-2xl shadow-xl border border-slate-100 overflow-hidden z-50 animate-slide-up">
                      <div class="bg-slate-50 px-4 py-3 border-b border-slate-100 flex justify-between items-center">
                          <span class="font-black text-xs text-slate-700 tracking-tight" data-translate="true">Notifications</span>
                          @if($pendingCount > 0)
                          <span class="bg-red-100 text-red-600 text-[9px] font-black px-2 py-0.5 rounded-full">{{ $pendingCount }} New</span>
                          @endif
                      </div>
                      <div class="max-h-64 overflow-y-auto custom-scroll">
                          @if($pendingCount > 0)
                              <div onclick="goToApproval()" class="p-4 border-b border-slate-50 hover:bg-red-50 cursor-pointer transition flex gap-3 group">
                                  <div class="w-8 h-8 rounded-full bg-red-100 text-red-500 flex items-center justify-center flex-shrink-0 group-hover:bg-red-600 group-hover:text-white transition"><i class="fas fa-exclamation-circle text-xs"></i></div>
                                  <div>
                                      <p class="text-xs font-black text-slate-800 mb-0.5" data-translate="true">Tindakan Diperlukan</p>
                                      <p class="text-[10px] text-slate-500 leading-tight">Anda memiliki <span class="font-bold text-red-600">{{ $pendingCount }}</span> dokumen GIS yang menunggu aksi Anda.</p>
                                      <p class="text-[9px] text-red-600 font-bold mt-1.5 opacity-0 group-hover:opacity-100 transition transform group-hover:translate-x-1" data-translate="true">Klik untuk memproses &rarr;</p>
                                  </div>
                              </div>
                          @else
                              <div class="p-6 text-center text-slate-400">
                                  <i class="far fa-check-circle text-3xl mb-3 opacity-30"></i>
                                  <p class="text-xs font-bold" data-translate="true">Semua selesai.</p>
                                  <p class="text-[10px] mt-1" data-translate="true">Tidak ada tugas yang tertunda.</p>
                              </div>
                          @endif
                      </div>
                  </div>
              </div>

              @if($isAdmin)
              <button onclick="openSettingsModal()" class="w-8 h-8 sm:w-10 sm:h-10 bg-white rounded-full flex items-center justify-center text-slate-500 hover:text-red-600 shadow-sm transition btn-animated hidden sm:flex" title="System Settings"><i class="fas fa-cog text-red-500 animate-[spin_4s_linear_infinite]"></i></button>
              <button onclick="openManageUsers()" class="w-8 h-8 sm:w-10 sm:h-10 bg-white rounded-full flex items-center justify-center text-slate-500 hover:text-red-600 shadow-sm transition btn-animated hidden sm:flex" title="Manage Users"><i class="fas fa-users-cog"></i></button>
              @endif

              <div class="h-6 w-px bg-slate-200 hidden sm:block mx-1"></div>
              <div class="flex items-center gap-2 sm:gap-3 cursor-pointer group" onclick="openProfileModal()">
                  <div class="hidden md:block text-right">
                      <div class="text-xs sm:text-sm font-black text-slate-800 leading-tight">{{ $currentUser->fullname }}</div>
                      <div class="text-[9px] sm:text-[10px] font-bold text-slate-500">{{ $role }}</div>
                  </div>
                  <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-full bg-gradient-to-tr from-red-500 to-red-700 text-white flex items-center justify-center font-black shadow-md group-hover:shadow-lg transition text-xs sm:text-sm">
                      {{ substr($currentUser->fullname, 0, 1) }}
                  </div>
              </div>
              <button onclick="logoutAction()" class="w-8 h-8 sm:w-10 sm:h-10 bg-rose-50 rounded-full flex items-center justify-center text-rose-500 hover:bg-rose-600 hover:text-white shadow-sm transition ml-0 sm:ml-1 btn-animated" title="Logout"><i class="fas fa-sign-out-alt text-xs sm:text-sm"></i></button>
          </div>
      </header>

      <main class="flex-1 overflow-x-hidden overflow-y-auto bg-[#f4f7fe] px-3 sm:px-8 py-4 sm:py-6 custom-scroll pb-24">

<script>
    function toggleNotifDropdown() { document.getElementById('notif-dropdown').classList.toggle('hidden'); }
    document.addEventListener('click', function(e) {
        if(!e.target.closest('#notif-dropdown') && !e.target.closest('[onclick="toggleNotifDropdown()"]')) {
            const notifDd = document.getElementById('notif-dropdown');
            if(notifDd && !notifDd.classList.contains('hidden')) notifDd.classList.add('hidden');
        }
    });

    function goToApproval() {
        document.getElementById('notif-dropdown').classList.add('hidden');
        const targetFilter = "{{ $targetFilter }}";
        if(targetFilter !== '') {
            localStorage.setItem('auto_filter_gi', targetFilter);
            window.location.href = "{{ url('/gi') }}";
        }
    }

    document.addEventListener("DOMContentLoaded", () => {
        const autoFilter = localStorage.getItem('auto_filter_gi');
        if (autoFilter && typeof setGiFilter === 'function') {
            let checkDataInterval = setInterval(() => {
                if(typeof giData !== 'undefined' && giData.length > 0) {
                    clearInterval(checkDataInterval);
                    localStorage.removeItem('auto_filter_gi');
                    setGiFilter(autoFilter);
                }
            }, 300);
            setTimeout(() => { clearInterval(checkDataInterval); localStorage.removeItem('auto_filter_gi'); }, 5000);
        }
    });
</script>

  <div id="modal-prompt-custom" class="hidden fixed inset-0 bg-slate-900/70 backdrop-blur-sm z-[100] flex items-center justify-center p-4">
      <div class="bg-white rounded-3xl w-full max-w-sm shadow-2xl animate-slide-up overflow-hidden">
          <div class="p-6 sm:p-8 text-center">
              <div class="w-14 h-14 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-4 text-indigo-600 shadow-inner"><i class="fas fa-edit text-xl"></i></div>
              <h3 class="text-lg font-black text-slate-800 mb-2 tracking-tight" id="prompt-custom-title" data-translate="true">Edit Data</h3>
              <p class="text-xs sm:text-sm text-slate-500 mb-4 leading-relaxed font-medium" id="prompt-custom-msg" data-translate="true">Masukkan nilai baru:</p>
              <input type="text" id="prompt-custom-input" class="w-full border border-slate-300 rounded-xl p-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition font-bold text-center mb-6" placeholder="Ketik disini...">
              <div class="flex gap-2">
                  <button onclick="closeModal('modal-prompt-custom')" class="flex-1 py-3 border-2 border-slate-200 text-slate-600 rounded-xl font-bold text-xs hover:bg-slate-50 transition btn-animated" data-translate="true" data-i18n="btn_cancel">Batal</button>
                  <button onclick="executeCustomPrompt()" class="flex-1 py-3 bg-indigo-600 text-white rounded-xl font-bold text-xs hover:bg-indigo-700 shadow-md transition btn-animated" data-translate="true">Simpan</button>
              </div>
          </div>
      </div>
  </div>

  <div id="modal-settings" class="hidden fixed inset-0 bg-slate-900/70 backdrop-blur-sm z-[70] flex items-center justify-center p-3 sm:p-4">
      <div class="bg-white rounded-3xl w-full max-w-sm shadow-2xl animate-slide-up overflow-hidden">
          <div class="bg-slate-800 px-5 py-4 border-b border-slate-700 flex justify-between items-center text-white"><h3 class="font-bold text-sm sm:text-lg tracking-tight"><i class="fas fa-cog text-red-400 mr-2"></i> <span data-translate="true">System Settings</span></h3><button onclick="closeModal('modal-settings')" class="text-slate-400 hover:text-white transition"><i class="fas fa-times text-lg"></i></button></div>
          <div class="p-6 sm:p-8"><div class="mb-6"><label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2" data-translate="true">Marquee Speed (Detik)</label><p class="text-[10px] sm:text-xs text-slate-400 mb-3">Semakin kecil angkanya, semakin cepat teks statistik berjalan.</p><input type="number" id="set-speed" class="w-full border border-slate-300 rounded-xl p-3 text-sm focus:ring-2 focus:ring-red-500 outline-none transition font-black text-center" min="10" max="300" placeholder="Contoh: 80"></div><div class="flex gap-2 sm:gap-3"><button onclick="closeModal('modal-settings')" class="flex-1 py-3 border border-slate-200 text-slate-600 rounded-xl font-bold text-xs sm:text-sm hover:bg-slate-50 transition btn-animated">Cancel</button><button onclick="saveSettings()" class="flex-1 py-3 bg-red-600 text-white rounded-xl font-bold text-xs sm:text-sm hover:bg-red-700 shadow-md transition btn-animated">Save</button></div></div>
      </div>
  </div>

  <div id="modal-export" class="hidden fixed inset-0 bg-slate-900/70 backdrop-blur-sm z-[70] flex items-center justify-center p-3 sm:p-4">
      <div class="bg-white rounded-3xl w-full max-w-md shadow-2xl animate-slide-up overflow-hidden">
          <div class="bg-slate-800 px-5 py-4 border-b border-slate-700 flex justify-between items-center text-white"><h3 class="font-bold text-sm sm:text-lg tracking-tight"><i class="fas fa-print text-red-400 mr-2"></i> <span data-translate="true" data-i18n="export_data_title">Export Report</span></h3><button onclick="closeModal('modal-export')" class="text-slate-400 hover:text-white transition"><i class="fas fa-times text-lg"></i></button></div>
          <div class="p-6 sm:p-8"><div class="mb-5"><label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2" data-translate="true" data-i18n="export_type">Data Type</label><select id="exp-type" class="w-full border border-slate-300 rounded-xl p-3 text-sm focus:ring-2 focus:ring-red-500 outline-none transition font-medium" onchange="toggleExpDates()"><option value="GI" data-translate="true">Good Issue History</option><option value="GR" data-translate="true">Good Receive History</option><option value="INV" data-translate="true">Master Inventory (Current)</option></select></div><div id="exp-date-group" class="grid grid-cols-2 gap-3 sm:gap-4 mb-6"><div><label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2" data-translate="true" data-i18n="start_date">Start Date</label><input type="date" id="exp-start" class="w-full border border-slate-300 rounded-xl p-3 text-xs sm:text-sm focus:ring-2 focus:ring-red-500 outline-none transition"></div><div><label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2" data-translate="true" data-i18n="end_date">End Date</label><input type="date" id="exp-end" class="w-full border border-slate-300 rounded-xl p-3 text-xs sm:text-sm focus:ring-2 focus:ring-red-500 outline-none transition"></div></div><div class="flex gap-2 sm:gap-3"><button onclick="processExport('excel')" class="flex-1 py-3 bg-emerald-600 text-white rounded-xl font-bold text-xs sm:text-sm hover:bg-emerald-700 shadow-md transition btn-animated"><i class="fas fa-file-excel mr-1.5"></i> Excel</button><button onclick="processExport('pdf')" class="flex-1 py-3 bg-rose-600 text-white rounded-xl font-bold text-xs sm:text-sm hover:bg-rose-700 shadow-md transition btn-animated"><i class="fas fa-file-pdf mr-1.5"></i> PDF</button></div><div id="exp-loading" class="hidden text-center mt-5 text-xs font-bold text-red-600 animate-pulse"><i class="fas fa-spinner fa-spin mr-2"></i> <span data-translate="true">Processing Data...</span></div></div>
      </div>
  </div>

  <div id="modal-alert-custom" class="hidden fixed inset-0 bg-slate-900/70 backdrop-blur-sm z-[100] flex items-center justify-center p-4">
      <div class="bg-white rounded-3xl w-full max-w-xs shadow-2xl animate-slide-up overflow-hidden">
          <div class="p-6 sm:p-8 text-center"><div class="w-14 h-14 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4 text-red-600 shadow-inner"><i class="fas fa-info text-xl"></i></div><h3 class="text-lg font-black text-slate-800 mb-2 tracking-tight" id="alert-custom-title" data-translate="true">Information</h3><p class="text-xs sm:text-sm text-slate-500 mb-6 leading-relaxed font-medium" id="alert-custom-msg" data-translate="true">Message</p><button onclick="closeModal('modal-alert-custom')" class="w-full py-3 bg-slate-800 text-white rounded-xl font-bold text-xs sm:text-sm hover:bg-slate-900 shadow-md transition btn-animated" data-translate="true" data-i18n="btn_ok">OK</button></div>
      </div>
  </div>

  <div id="modal-confirm-custom" class="hidden fixed inset-0 bg-slate-900/70 backdrop-blur-sm z-[100] flex items-center justify-center p-4">
      <div class="bg-white rounded-3xl w-full max-w-xs shadow-2xl animate-slide-up overflow-hidden">
          <div class="p-6 sm:p-8 text-center"><div class="w-14 h-14 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4 text-blue-600 shadow-inner"><i class="fas fa-question text-xl"></i></div><h3 class="text-lg font-black text-slate-800 mb-2 tracking-tight" id="confirm-custom-title" data-translate="true">Confirm</h3><p class="text-xs sm:text-sm text-slate-500 mb-6 leading-relaxed font-medium" id="confirm-custom-msg" data-translate="true">Are you sure?</p><div class="flex gap-2"><button onclick="closeModal('modal-confirm-custom')" class="flex-1 py-3 border-2 border-slate-200 text-slate-600 rounded-xl font-bold text-xs hover:bg-slate-50 transition btn-animated" data-translate="true" data-i18n="btn_cancel">Cancel</button><button onclick="executeCustomConfirm()" class="flex-1 py-3 bg-blue-600 text-white rounded-xl font-bold text-xs hover:bg-blue-700 shadow-md transition btn-animated" data-translate="true" data-i18n="btn_yes">Proceed</button></div></div>
      </div>
  </div>

  <div id="modal-profile" class="hidden fixed inset-0 bg-slate-900/70 backdrop-blur-sm z-[60] flex items-center justify-center p-3 sm:p-4">
      <div class="bg-white rounded-3xl w-full max-w-md shadow-2xl flex flex-col max-h-[90vh] animate-slide-up overflow-hidden">
          <div class="bg-red-600 px-5 py-4 flex justify-between items-center flex-none"><h3 class="font-bold text-white tracking-wide text-sm sm:text-base"><i class="fas fa-user-edit mr-2"></i> <span data-translate="true" data-i18n="my_profile">My Profile</span></h3><button onclick="closeModal('modal-profile')" class="text-red-200 hover:text-white"><i class="fas fa-times text-lg"></i></button></div>
          <div class="p-5 sm:p-6 overflow-y-auto flex-1 custom-scroll"><div class="grid grid-cols-2 gap-4 mb-4"><div class="col-span-1"><label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">NIK</label><input type="text" id="prof-nik" class="w-full bg-slate-100 border border-slate-200 rounded-xl p-2.5 text-xs text-slate-600 font-bold" readonly disabled></div><div class="col-span-1"><label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5" data-translate="true" data-i18n="dept">Department</label><input type="text" id="prof-dept" class="w-full bg-slate-100 border border-slate-200 rounded-xl p-2.5 text-xs text-slate-600 font-bold" readonly disabled></div><div class="col-span-2"><label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5" data-translate="true" data-i18n="fullname">Fullname</label><input type="text" id="prof-name" class="w-full bg-slate-100 border border-slate-200 rounded-xl p-2.5 text-xs text-slate-600 font-bold" readonly disabled></div></div><div class="border-t border-slate-200 pt-4 mt-2"><div class="mb-4"><label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5"><span data-translate="true" data-i18n="wa_phone">No WhatsApp</span> <span class="text-blue-500 lowercase font-medium italic" data-translate="true" data-i18n="editable">(Dapat Diubah)</span></label><input type="tel" id="prof-phone" class="w-full border border-slate-300 rounded-xl p-2.5 text-xs focus:ring-2 focus:ring-red-500 outline-none transition" placeholder="Contoh: 0812345678"></div><div class="mb-2"><label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5"><span data-translate="true" data-i18n="new_pass">Password Baru</span> <span class="text-slate-400 lowercase font-medium italic" data-translate="true" data-i18n="pass_note">(Kosongkan jika tidak diubah)</span></label><input type="password" id="prof-pass" class="w-full border border-slate-300 rounded-xl p-2.5 text-xs focus:ring-2 focus:ring-red-500 outline-none transition" placeholder="******"></div></div></div>
          <div class="p-4 border-t border-slate-100 bg-slate-50 flex justify-end gap-2 flex-none rounded-b-3xl"><button onclick="closeModal('modal-profile')" class="px-5 py-2 text-slate-600 border border-slate-300 hover:bg-slate-200 rounded-xl text-xs font-bold transition btn-animated" data-translate="true" data-i18n="btn_cancel">Cancel</button><button onclick="saveProfile()" id="btn-save-profile" class="px-5 py-2 bg-red-600 text-white rounded-xl font-bold text-xs shadow-md btn-animated hover:bg-red-700" data-translate="true" data-i18n="btn_update_prof">Update</button></div>
      </div>
  </div>

  <div id="modal-image-viewer" class="hidden fixed inset-0 bg-slate-900/95 backdrop-blur-md z-[110] flex items-center justify-center p-4 cursor-pointer" onclick="closeModal('modal-image-viewer')">
      <div class="relative w-full max-w-3xl flex justify-center items-center animate-slide-up" onclick="event.stopPropagation()"><button onclick="closeModal('modal-image-viewer')" class="absolute -top-12 right-0 text-white/70 hover:text-white text-4xl transition hover:scale-110">&times;</button><img id="img-viewer-src" src="" class="max-w-full max-h-[80vh] rounded-xl shadow-2xl object-contain border border-white/10 bg-slate-800"></div>
  </div>

  <div id="modal-users" class="hidden fixed inset-0 bg-slate-900/70 backdrop-blur-sm z-[60] flex items-center justify-center p-2 sm:p-4">
    <div class="bg-white rounded-3xl w-full max-w-5xl shadow-2xl flex flex-col h-[95vh] sm:h-[85vh] animate-slide-up overflow-hidden">
        <div class="bg-slate-800 px-5 py-4 flex justify-between items-center flex-none"><h3 class="font-bold text-white tracking-wide text-sm sm:text-base"><i class="fas fa-users-cog text-red-400 mr-2"></i> <span data-translate="true">Manage Users</span></h3><button onclick="closeModal('modal-users')" class="text-slate-400 hover:text-white transition"><i class="fas fa-times text-lg"></i></button></div>
        <div class="flex flex-col sm:flex-row flex-1 overflow-hidden">
            <div class="w-full sm:w-1/3 border-b sm:border-b-0 sm:border-r border-slate-200 flex flex-col bg-slate-50 h-1/3 sm:h-full">
                <div class="p-4 border-b border-slate-200"><button onclick="resetUserForm()" class="w-full bg-red-600 text-white py-2 rounded-xl font-bold text-xs mb-3 shadow-md hover:bg-red-700 btn-animated"><i class="fas fa-plus mr-1"></i> <span data-translate="true">New User</span></button><div class="relative"><span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400"><i class="fas fa-search text-xs"></i></span><input type="text" id="search-user" onkeyup="filterUsers()" class="w-full border border-slate-300 rounded-xl p-2 pl-8 text-xs outline-none focus:ring-2 focus:ring-red-500 shadow-sm transition" data-translate-ph="true" placeholder="Search user..."></div></div>
                <div id="user-list" class="flex-1 overflow-y-auto custom-scroll p-2 space-y-1.5"></div>
            </div>
            <div class="w-full sm:w-2/3 p-4 sm:p-6 overflow-y-auto custom-scroll bg-white h-2/3 sm:h-full">
                <h4 class="font-black text-base mb-4 text-slate-800 tracking-tight border-b pb-2" id="form-title" data-translate="true">Create User</h4>
                <form id="user-form" onsubmit="event.preventDefault(); saveUser();">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                        <div class="col-span-1"><label class="block text-[9px] sm:text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1" data-translate="true">Username</label><input type="text" id="u-user" class="w-full border border-slate-300 rounded-xl p-2.5 text-xs outline-none focus:ring-2 focus:ring-red-500 bg-slate-50 transition" required></div>
                        <div class="col-span-1"><label class="block text-[9px] sm:text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1" data-translate="true">Password</label><input type="password" id="u-pass" class="w-full border border-slate-300 rounded-xl p-2.5 text-xs outline-none focus:ring-2 focus:ring-red-500 transition" required placeholder="******"></div>
                        <div class="col-span-1 sm:col-span-2"><label class="block text-[9px] sm:text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1" data-translate="true">Fullname</label><input type="text" id="u-name" class="w-full border border-slate-300 rounded-xl p-2.5 text-xs outline-none focus:ring-2 focus:ring-red-500 transition" required></div>
                        <div class="col-span-1"><label class="block text-[9px] sm:text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">NIK</label><input type="text" id="u-nik" class="w-full border border-slate-300 rounded-xl p-2.5 text-xs outline-none focus:ring-2 focus:ring-red-500 transition" placeholder="ID"></div>
                        <div class="col-span-1"><label class="block text-[9px] sm:text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1" data-translate="true">Department</label><input type="text" id="u-dept" class="w-full border border-slate-300 rounded-xl p-2.5 text-xs outline-none focus:ring-2 focus:ring-red-500 transition" required></div>
                        <div class="col-span-1"><label class="block text-[9px] sm:text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Role</label><select id="u-role" class="w-full border border-slate-300 rounded-xl p-2.5 text-xs outline-none focus:ring-2 focus:ring-red-500 bg-white transition" onchange="handleRoleChange(this)"><option value="User">User</option><option value="SectionHead">Section Head</option><option value="TeamLeader">Team Leader</option><option value="Warehouse">Warehouse</option><option value="PlantHead">Plant Head</option><option value="Administrator">Administrator</option></select></div>
                        <div class="col-span-1"><label class="block text-[9px] sm:text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Phone (WA)</label><input type="tel" id="u-phone" class="w-full border border-slate-300 rounded-xl p-2.5 text-xs outline-none focus:ring-2 focus:ring-red-500 transition"></div>

                        <div class="col-span-1 sm:col-span-2 border-t border-slate-200 pt-4 mt-2">
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2"><i class="fas fa-shield-alt text-red-500 mr-1"></i> <span data-translate="true">Access Rights (Permissions)</span></label>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-[10px] sm:text-xs bg-slate-50 p-4 rounded-xl border border-slate-100">
                                <label class="flex items-center gap-2 text-slate-700 font-medium cursor-pointer"><input type="checkbox" id="chk-gi-submit" class="acc-chk w-3 h-3 sm:w-4 sm:h-4 text-red-600 rounded focus:ring-red-500" value="gi_submit"> <span data-translate="true">Submit Good Issue</span></label>
                                <label class="flex items-center gap-2 text-slate-700 font-medium cursor-pointer"><input type="checkbox" id="chk-gr-submit" class="acc-chk w-3 h-3 sm:w-4 sm:h-4 text-red-600 rounded focus:ring-red-500" value="gr_submit"> <span data-translate="true">Submit Good Receive</span></label>
                                <label class="flex items-center gap-2 text-slate-700 font-medium cursor-pointer"><input type="checkbox" id="chk-item-add" class="acc-chk w-3 h-3 sm:w-4 sm:h-4 text-red-600 rounded focus:ring-red-500" value="item_add"> <span data-translate="true">Add Item Master</span></label>
                                <label class="flex items-center gap-2 text-slate-700 font-medium cursor-pointer"><input type="checkbox" id="chk-item-edit" class="acc-chk w-3 h-3 sm:w-4 sm:h-4 text-red-600 rounded focus:ring-red-500" value="item_edit"> <span data-translate="true">Edit Item Info</span></label>
                                <label class="flex items-center gap-2 text-slate-700 font-medium cursor-pointer"><input type="checkbox" id="chk-stock-edit" class="acc-chk w-3 h-3 sm:w-4 sm:h-4 text-red-600 rounded focus:ring-red-500" value="stock_edit"> <span data-translate="true">Edit/Adjust Stock</span></label>
                                <label class="flex items-center gap-2 text-slate-700 font-medium cursor-pointer"><input type="checkbox" id="chk-export-data" class="acc-chk w-3 h-3 sm:w-4 sm:h-4 text-red-600 rounded focus:ring-red-500" value="export_data"> <span data-translate="true">Export Data (PDF/Excel)</span></label>
                                <label class="flex items-center gap-2 text-slate-700 font-medium cursor-pointer"><input type="checkbox" id="chk-price-add" class="acc-chk w-3 h-3 sm:w-4 sm:h-4 text-red-600 rounded focus:ring-red-500" value="price_add"> <span data-translate="true" data-i18n="price_add">Input Harga Baru</span></label>
                                <label class="flex items-center gap-2 text-slate-700 font-medium cursor-pointer"><input type="checkbox" id="chk-price-edit" class="acc-chk w-3 h-3 sm:w-4 sm:h-4 text-red-600 rounded focus:ring-red-500" value="price_edit"> <span data-translate="true" data-i18n="price_edit">Edit Harga (Exist)</span></label>
                                <label class="flex items-center gap-2 text-slate-700 font-medium cursor-pointer"><input type="checkbox" id="chk-item-delete" class="acc-chk w-3 h-3 sm:w-4 sm:h-4 text-red-500 rounded focus:ring-red-500" value="item_delete"> <span data-translate="true" class="text-red-600" data-i18n="item_delete">Hapus Item</span></label>
                                <label class="flex items-center gap-2 text-slate-700 font-medium cursor-pointer"><input type="checkbox" id="chk-edit-gi-no" class="acc-chk w-3 h-3 sm:w-4 sm:h-4 text-indigo-600 rounded focus:ring-indigo-500" value="edit_gi_no"> <span data-translate="true" class="text-indigo-700">Edit Nomor GI</span></label>
                                <label class="flex items-center gap-2 text-slate-700 font-medium cursor-pointer"><input type="checkbox" id="chk-edit-gr-no" class="acc-chk w-3 h-3 sm:w-4 sm:h-4 text-indigo-600 rounded focus:ring-indigo-500" value="edit_gr_no"> <span data-translate="true" class="text-indigo-700">Edit Nomor GR</span></label>
                            </div>
                        </div>

                        <div class="col-span-1 sm:col-span-2 mt-2 flex justify-between gap-2 pt-4 border-t border-slate-100">
                            <button type="button" id="btn-del-user" onclick="deleteUser()" class="hidden bg-red-100 text-red-600 px-4 py-2 rounded-xl text-xs font-bold hover:bg-red-200 btn-animated"><i class="fas fa-trash"></i></button>
                            <button type="submit" class="bg-red-600 text-white px-6 py-2 rounded-xl shadow-md font-bold text-xs w-full sm:w-auto ml-auto btn-animated hover:bg-red-700" data-translate="true">Save User</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
  </div>

  <script>
    // FUNGSI PROMPT
    let globalPromptCallback = null;
    function showCustomPrompt(title, message, defaultValue, callback) {
        document.getElementById('prompt-custom-title').innerText = title;
        document.getElementById('prompt-custom-msg').innerText = message;
        document.getElementById('prompt-custom-input').value = defaultValue || '';
        globalPromptCallback = callback;
        openModal('modal-prompt-custom');
        setTimeout(() => document.getElementById('prompt-custom-input').focus(), 100);
        AutoTranslator.processDOM();
    }
    function executeCustomPrompt() {
        const val = document.getElementById('prompt-custom-input').value;
        if(globalPromptCallback) globalPromptCallback(val);
        closeModal('modal-prompt-custom');
        globalPromptCallback = null;
    }

    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        if (sidebar.classList.contains('-translate-x-full')) {
            sidebar.classList.remove('-translate-x-full');
            overlay.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            setTimeout(() => overlay.classList.remove('opacity-0'), 10);
        } else {
            sidebar.classList.add('-translate-x-full');
            overlay.classList.add('opacity-0');
            document.body.classList.remove('overflow-hidden');
            setTimeout(() => overlay.classList.add('hidden'), 300);
        }
    }

    const currentUser = @json($currentUser);

    // LOGIKA PARSING RIGHTS YANG 100% AMAN!
    let rawRights = @json($rights);
    let userRights = [];
    if (typeof rawRights === 'string') {
        try { userRights = JSON.parse(rawRights); } catch(e) { userRights = []; }
    } else if (Array.isArray(rawRights)) {
        userRights = rawRights;
    }

    let inventoryData = [];
    let globalConfirmCallback = null;
    let allUsers = [];
    let currentLang = localStorage.getItem('portal_lang') || 'en';

    const IDLE_TIMEOUT_MINUTES = 2;
    let idleTime = 0; let idleInterval;
    function resetIdleTimer() { idleTime = 0; }
    function checkIdleTime() { idleTime++; if (idleTime >= IDLE_TIMEOUT_MINUTES) { clearInterval(idleInterval); logoutAction(); } }
    idleInterval = setInterval(checkIdleTime, 60000);
    ['mousemove', 'keydown', 'mousedown', 'touchstart', 'scroll', 'click'].forEach(evt => { document.addEventListener(evt, resetIdleTimer, { capture: true, passive: true }); });

    const i18n = {
        en: { app_desc: "Good Issue & Inventory System", forgot_pass: "Forgot Password?", login: "Login", tab_gi: "Good Issue (GI)", tab_gr: "Good Receive (GR)", tab_inv: "Inventory", stat_tot_gi: "Total Request", stat_pend: "Pending Approval", stat_comp: "Completed", stat_pend_head: "Pending Head", stat_pend_wh: "Pending WH", stat_pend_recv: "Pending Receive", hist_gi: "Good Issue History", click_filter_info: "Click statistics cards above to filter data.", ph_search_gi: "Search GI (ID/Name/Dept/Desc)...", btn_new_gi: "New GI Form", th_id: "NO GIF & Date", th_req: "Requestor Info", th_items: "Items & Activities Description", th_stat: "Status", th_act: "Action", hist_gr: "Good Receive History", desc_gr: "Log of incoming items to warehouse.", ph_search_gr: "Search GR (ID/Name/Supplier)...", btn_new_gr: "New GR Form", th_gr_id: "GR ID & Date", th_gr_by: "Received By", th_gr_rem: "Remarks / Supplier", th_gr_items: "Items Received", mast_inv: "Master Inventory", desc_inv: "Manage warehouse items and stock.", btn_add_item: "Add Item Master", th_it_code: "Item Code", th_it_name: "Item Name", th_it_spec: "Specification", th_it_cat: "Category", th_it_stock: "Stock / UoM", btn_ok: "OK", btn_cancel: "Cancel", btn_yes: "Yes, Proceed", reset_pass: "Reset Password", reset_info: "Reset link will be sent to your WhatsApp number.", btn_send_wa: "Send WhatsApp Link", my_profile: "My Profile", dept: "Department", fullname: "Fullname", wa_phone: "WhatsApp No.", editable: "(Editable)", new_pass: "New Password", pass_note: "(Leave blank if unchanged)", btn_update_prof: "Update Profile", form_gi: "Form Good Issue", req_dept: "Requestor / Dept", sec_req: "Section Requestor", ph_sec_req: "Ex: Maintenance / Production", act_desc: "Activities Description", ph_act_desc: "Explain activities or reason...", item_list: "Item List", add_row: "Add Item Row", curr_stk_short: "Curr. Stock", req_qty: "Requested Qty", rsn_code: "Reason Code", cost_ctr: "Cost Center *", btn_submit_form: "Submit Form", form_gr: "Form Good Receive (GR)", info_gr: "Input incoming items. Data will automatically update Master Inventory stock.", rem_supp: "Remarks / Supplier / PO Number", inc_items: "Incoming Items", qty_recv: "Qty Received", btn_save_stk: "Save & Update Stock", master_item: "Master Item", it_code: "Item No (Code)", it_name: "Item Name", it_spec: "Item Specification", cat: "Category", curr_stk: "Current Stock", btn_save_item: "Save Item", rej_req: "Reject Request", ph_rej: "Reason for rejection...", btn_conf_rej: "Confirm Reject", no_data: "No data found.", btn_appr: "Approve", btn_rej: "Reject", btn_iss: "Issue Items", err_conn: "Connection Error.", err_req: "Please fill all required fields.", ph_search_item: "Search Item...", ph_search_inv: "Search Item (Code/Name)...", btn_export_data: "Export Report", export_data_title: "Export Report", export_type: "Data Type", start_date: "Start Date", end_date: "End Date", btn_export_items: "Export", btn_import_items: "Import", btn_template: "Template", btn_confirm_recv: "Confirm Receive", th_price: "Price", total_price: "Total Price", price_add: "Add New Price", price_edit: "Edit Existing Price", item_delete: "Delete Item", grand_total: "Grand Total" },
        id: { app_desc: "Sistem Pengeluaran & Inventaris", forgot_pass: "Lupa Password?", login: "Masuk", tab_gi: "Pengeluaran (GI)", tab_gr: "Penerimaan (GR)", tab_inv: "Inventaris", stat_tot_gi: "Total Permintaan", stat_pend: "Menunggu Persetujuan", stat_comp: "Selesai", stat_pend_head: "Menunggu Head", stat_pend_wh: "Menunggu Gudang", stat_pend_recv: "Menunggu Diterima", hist_gi: "Riwayat Pengeluaran (GI)", click_filter_info: "Klik kartu statistik di atas untuk memfilter data.", ph_search_gi: "Cari GI (ID/Nama/Dept/Desk)...", btn_new_gi: "Form GI Baru", th_id: "NO GIF & Tanggal", th_req: "Info Pemohon", th_items: "Barang & Deskripsi Aktivitas", th_stat: "Status", th_act: "Aksi", hist_gr: "Riwayat Penerimaan (GR)", desc_gr: "Catatan barang masuk ke gudang.", ph_search_gr: "Cari GR (ID/Nama/Suplier)...", btn_new_gr: "Form GR Baru", th_gr_id: "ID GR & Tanggal", th_gr_by: "Diterima Oleh", th_gr_rem: "Catatan / Suplier", th_gr_items: "Barang Diterima", mast_inv: "Master Inventaris", desc_inv: "Kelola stok dan barang gudang.", btn_add_item: "Tambah Master Barang", th_it_code: "Kode Barang", th_it_name: "Nama Barang", th_it_spec: "Spesifikasi", th_it_cat: "Kategori", th_it_stock: "Stok / Satuan", btn_ok: "OK", btn_cancel: "Batal", btn_yes: "Ya, Lanjutkan", reset_pass: "Reset Kata Sandi", reset_info: "Tautan reset akan dikirim ke nomor WhatsApp Anda.", btn_send_wa: "Kirim Tautan WA", my_profile: "Profil Saya", dept: "Departemen", fullname: "Nama Lengkap", wa_phone: "No. WhatsApp", editable: "(Dapat Diubah)", new_pass: "Kata Sandi Baru", pass_note: "(Kosongkan jika tidak diubah)", btn_update_prof: "Perbarui Profil", form_gi: "Formulir Pengeluaran (GI)", req_dept: "Pemohon / Dept", sec_req: "Seksi Pemohon", ph_sec_req: "Cth: Maintenance / Produksi", act_desc: "Deskripsi Aktivitas", ph_act_desc: "Jelaskan aktivitas atau alasan...", item_list: "Daftar Barang", add_row: "Tambah Baris", curr_stk_short: "Sisa Stok", req_qty: "Jumlah Diminta", rsn_code: "Kode Alasan", cost_ctr: "Pusat Biaya *", btn_submit_form: "Kirim Formulir", form_gr: "Formulir Penerimaan (GR)", info_gr: "Input barang masuk. Data akan otomatis menambah stok Master Inventaris.", rem_supp: "Catatan / Suplier / No. PO", inc_items: "Barang Masuk", qty_recv: "Jml Diterima", btn_save_stk: "Simpan & Perbarui Stok", master_item: "Master Barang", it_code: "No/Kode Barang", it_name: "Nama Barang", it_spec: "Spesifikasi Barang", cat: "Kategori", curr_stk: "Stok Saat Ini", btn_save_item: "Simpan Barang", rej_req: "Tolak Permintaan", ph_rej: "Alasan penolakan...", btn_conf_rej: "Konfirmasi Tolak", no_data: "Tidak ada data.", btn_appr: "Setujui", btn_rej: "Tolak", btn_iss: "Keluarkan Barang", err_conn: "Koneksi Gagal.", err_req: "Harap isi semua kolom wajib.", ph_search_item: "Cari Barang...", ph_search_inv: "Cari Barang (Kode/Nama)...", btn_export_data: "Ekspor Laporan", export_data_title: "Ekspor Laporan", export_type: "Tipe Data", start_date: "Tanggal Mulai", end_date: "Tanggal Akhir", btn_export_items: "Ekspor", btn_import_items: "Impor", btn_template: "Template", btn_confirm_recv: "Konfirmasi Terima", th_price: "Harga", total_price: "Total Harga", price_add: "Input Harga Baru", price_edit: "Edit Harga (Exist)", item_delete: "Hapus Item", grand_total: "Total Keseluruhan" }
    };
    const t = (key) => i18n[currentLang][key] || key;

    const AutoTranslator = {
        cache: JSON.parse(localStorage.getItem('portal_auto_trans_cache')) || {},
        async translate(text, targetLang) {
            if (!text || !text.trim()) return text;
            const key = text.trim() + "_" + targetLang;
            if (this.cache[key]) return this.cache[key];
            try { const res = await fetch(`https://translate.googleapis.com/translate_a/single?client=gtx&sl=auto&tl=${targetLang}&dt=t&q=${encodeURIComponent(text.trim())}`); const data = await res.json(); const translated = data[0].map(x => x[0]).join(''); this.cache[key] = translated; localStorage.setItem('portal_auto_trans_cache', JSON.stringify(this.cache)); return translated; } catch(e) { return text; }
        },
        async processDOM() {
            const elements = document.querySelectorAll('[data-translate="true"]');
            for(let el of elements) {
                let originalText = el.getAttribute('data-orig-text');
                if(!originalText) { originalText = el.innerText.trim(); el.setAttribute('data-orig-text', originalText); }
                if(!originalText) continue;
                const i18nKey = el.getAttribute('data-i18n');
                if(i18nKey && i18n[currentLang] && i18n[currentLang][i18nKey]) { el.innerHTML = i18n[currentLang][i18nKey]; continue; }
                const translated = await this.translate(originalText, currentLang);
                if(el.innerText !== translated) el.innerText = translated;
            }
            const inputs = document.querySelectorAll('[data-translate-ph="true"]');
            for(let el of inputs) {
                let originalPh = el.getAttribute('data-orig-ph');
                if(!originalPh) { originalPh = el.placeholder.trim(); el.setAttribute('data-orig-ph', originalPh); }
                if(!originalPh) continue;
                const i18nKey = el.getAttribute('data-i18n-ph');
                if(i18nKey && i18n[currentLang] && i18n[currentLang][i18nKey]) { el.placeholder = i18n[currentLang][i18nKey]; continue; }
                const translated = await this.translate(originalPh, currentLang);
                if(el.placeholder !== translated) el.placeholder = translated;
            }
        }
    };

    async function applyLanguage() {
        document.getElementById('lang-label').innerText = currentLang.toUpperCase();
        document.querySelectorAll('[data-i18n]:not([data-translate="true"])').forEach(el => { const k = el.getAttribute('data-i18n'); if(i18n[currentLang] && i18n[currentLang][k]) el.innerHTML = i18n[currentLang][k]; });
        document.querySelectorAll('[data-i18n-ph]:not([data-translate-ph="true"])').forEach(el => { const k = el.getAttribute('data-i18n-ph'); if(i18n[currentLang] && i18n[currentLang][k]) el.placeholder = i18n[currentLang][k]; });
        await AutoTranslator.processDOM();
    }

    function toggleLanguage() { currentLang = (currentLang === 'en') ? 'id' : 'en'; localStorage.setItem('portal_lang', currentLang); window.location.reload(); }

    function logoutAction() { fetch('api/auth.php', { method:'POST', body:JSON.stringify({action:'logout'}) }).then(() => { window.location.href = '/login'; }).catch(() => { window.location.href = '/login'; }); }

    function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
    function closeModal(id) { document.getElementById(id).classList.add('hidden'); if(id === 'modal-action-photo' && typeof stopCamera === 'function') stopCamera(); if(id === 'modal-gr' && typeof stopGrCamera === 'function') stopGrCamera(); if(id === 'modal-image-viewer') { setTimeout(()=> {document.getElementById('img-viewer-src').src = '';}, 300); } }

    function openSettingsModal() { document.getElementById('set-speed').value = localStorage.getItem('marquee_speed') || 80; openModal('modal-settings'); }
    function saveSettings() { let speed = document.getElementById('set-speed').value; if (speed < 10) speed = 10; localStorage.setItem('marquee_speed', speed); closeModal('modal-settings'); showCustomAlert("Success", "Kecepatan animasi berhasil disimpan! Halaman akan dimuat ulang."); setTimeout(() => window.location.reload(), 2000); }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' || e.keyCode === 27) {
            const alertModal = document.getElementById('modal-alert-custom'); const confirmModal = document.getElementById('modal-confirm-custom'); const imgViewer = document.getElementById('modal-image-viewer');
            if (alertModal && !alertModal.classList.contains('hidden')) { closeModal('modal-alert-custom'); return; }
            if (confirmModal && !confirmModal.classList.contains('hidden')) { closeModal('modal-confirm-custom'); return; }
            if (imgViewer && !imgViewer.classList.contains('hidden')) { closeModal('modal-image-viewer'); return; }
            const mainModals = ['modal-export', 'modal-profile', 'modal-gi', 'modal-reject', 'modal-action-photo', 'modal-gr', 'modal-item', 'modal-users', 'modal-settings', 'modal-prompt-custom'];
            mainModals.forEach(id => { const m = document.getElementById(id); if (m && !m.classList.contains('hidden')) { closeModal(id); } });
        }
    });

    function showCustomAlert(title, message) { document.getElementById('alert-custom-title').innerText = title; document.getElementById('alert-custom-msg').innerText = message; document.getElementById('alert-custom-title').setAttribute('data-orig-text', title); document.getElementById('alert-custom-msg').setAttribute('data-orig-text', message); openModal('modal-alert-custom'); AutoTranslator.processDOM(); }
    function showCustomConfirm(title, message, callback) { document.getElementById('confirm-custom-title').innerText = title; document.getElementById('confirm-custom-title').setAttribute('data-orig-text', title); document.getElementById('confirm-custom-msg').innerText = message; document.getElementById('confirm-custom-msg').setAttribute('data-orig-text', message); globalConfirmCallback = callback; openModal('modal-confirm-custom'); AutoTranslator.processDOM(); }
    function executeCustomConfirm() { if(globalConfirmCallback) globalConfirmCallback(); closeModal('modal-confirm-custom'); globalConfirmCallback = null; }

    function openProfileModal() { document.getElementById('prof-nik').value = currentUser.nik || '-'; document.getElementById('prof-name').value = currentUser.fullname; document.getElementById('prof-dept').value = currentUser.department; document.getElementById('prof-phone').value = currentUser.phone || ''; document.getElementById('prof-pass').value = ''; openModal('modal-profile'); }
    function saveProfile() {
        const phone = document.getElementById('prof-phone').value; const pass = document.getElementById('prof-pass').value; const btn = document.getElementById('btn-save-profile'); const orgHtml = btn.innerHTML;
        btn.disabled = true; btn.innerHTML = `<i class="fas fa-spinner fa-spin mr-1"></i> <span data-translate="true">Memproses...</span>`; AutoTranslator.processDOM();
        fetch('api/users.php', { method: 'POST', body: JSON.stringify({ action: 'updateProfile', username: currentUser.username, phone: phone, newPass: pass }) }).then(r => r.json()).then(res => { btn.disabled = false; btn.innerHTML = orgHtml; if(res.code === 401) { logoutAction(); return; } if(res.success) { showCustomAlert("Success", "Profil berhasil diperbarui."); closeModal('modal-profile'); } else { showCustomAlert("Error", res.message); } }).catch(e => { btn.disabled = false; btn.innerHTML = orgHtml; showCustomAlert("Error", t('err_conn')); });
    }

    function viewPhoto(url) { if (!url || url === 'null' || url === 'undefined' || url.trim() === '' || url === '0') { showCustomAlert(t('info'), "Tidak ada bukti foto."); return; } const viewer = document.getElementById('img-viewer-src'); const baseUrl = window.location.origin + '/'; viewer.src = baseUrl + url.trim() + '?t=' + new Date().getTime(); openModal('modal-image-viewer'); }

    function openExportModal() { document.getElementById('exp-type').value = 'GI'; document.getElementById('exp-start').value = ''; document.getElementById('exp-end').value = ''; toggleExpDates(); openModal('modal-export'); }
    function toggleExpDates() { const type = document.getElementById('exp-type').value; const dateGroup = document.getElementById('exp-date-group'); if(type === 'INV') { dateGroup.classList.add('hidden'); } else { dateGroup.classList.remove('hidden'); } }
    function processExport(format) {
        const type = document.getElementById('exp-type').value; let start = document.getElementById('exp-start').value; let end = document.getElementById('exp-end').value;
        if (type !== 'INV' && (!start || !end)) { showCustomAlert("Warning", "Silakan lengkapi tanggal mulai dan tanggal akhir."); return; }
        document.getElementById('exp-loading').classList.remove('hidden');
        fetch('api/gis.php', { method: 'POST', body: JSON.stringify({ action: 'exportData', role: currentUser.role, username: currentUser.username, export_type: type, start_date: start, end_date: end }) }).then(r => r.json()).then(res => { document.getElementById('exp-loading').classList.add('hidden'); if(res.code === 401) { logoutAction(); return; } if(res.success) { if(!res.data || res.data.length === 0) { showCustomAlert("Info", "Tidak ada data pada rentang waktu yang dipilih."); return; } if (format === 'excel') generateExcel(res.data, type); else generatePdf(res.data, type); closeModal('modal-export'); } else { showCustomAlert("Error", res.message); } }).catch(err => { document.getElementById('exp-loading').classList.add('hidden'); showCustomAlert("Error", t('err_conn')); });
    }

    function generateExcel(data, type) {
        const wb = XLSX.utils.book_new(); const baseUrl = window.location.origin + '/';
        let rows = [[`AUDIT REPORT - ${type === 'GI' ? 'GOOD ISSUE' : (type === 'GR' ? 'GOOD RECEIVE' : 'MASTER INVENTORY')}`], [`Generated By: ${currentUser.fullname} (${currentUser.role})`], [`Date: ${new Date().toLocaleString('id-ID')}`], []];
        if (type === 'GI') { let header = ["ID Request", "Tanggal", "Nomor GI ERP", "Nama", "Departemen", "Seksi", "Deskripsi Aktivitas", "Kode Barang", "Nama Barang", "Qty", "UoM", "Harga Satuan", "Total Harga", "Reason Code", "Cost Center", "Status L1", "Status WH", "Foto Issued", "Diterima Oleh", "Tanggal Terima", "Foto Received"]; rows.push(header); data.forEach(r => { const fIssue = (r.issue_photo && r.issue_photo !== '0') ? baseUrl + r.issue_photo : '-'; const fRecv = (r.receive_photo && r.receive_photo !== '0') ? baseUrl + r.receive_photo : '-'; r.items.forEach(i => { rows.push([r.req_id, r.created_at, r.erp_gi_no||'-', r.fullname, r.department, r.section, r.purpose, i.code, i.name, parseInt(i.qty), i.uom, parseFloat(i.price||0), parseFloat(i.price||0) * parseInt(i.qty), i.reason_code||'-', i.cost_center||'-', r.app_head, r.app_wh, fIssue, r.received_by||'-', r.receive_time||'-', fRecv]); }); }); } else if (type === 'GR') { let header = ["ID Receive", "Tanggal", "Nomor GR ERP", "Diterima Oleh", "Remarks / Supplier", "Link Foto Bukti", "Kode Barang", "Nama Barang", "Qty Masuk", "UoM", "Harga Satuan", "Total Harga"]; rows.push(header); data.forEach(r => { const fGr = (r.gr_photo && r.gr_photo !== '0') ? baseUrl + r.gr_photo : '-'; r.items.forEach(i => { rows.push([r.gr_id, r.created_at, r.erp_gr_no||'-', r.fullname, r.remarks, fGr, i.code, i.name, parseInt(i.qty), i.uom, parseFloat(i.price||0), parseFloat(i.price||0) * parseInt(i.qty)]); }); }); } else if (type === 'INV') { let header = ["Kode Barang", "Nama Barang", "Spesifikasi", "Kategori", "UoM", "Stok Terkini", "Harga", "Terakhir Update"]; rows.push(header); data.forEach(r => { rows.push([r.item_code, r.item_name, r.item_spec||'-', r.category, r.uom, parseInt(r.stock), parseFloat(r.price||0), r.last_updated]); }); }
        const ws = XLSX.utils.aoa_to_sheet(rows); XLSX.utils.book_append_sheet(wb, ws, "Report"); XLSX.writeFile(wb, `GIS_Report_${type}_${new Date().getTime()}.xlsx`);
    }

    function generatePdf(data, type) {
        const { jsPDF } = window.jspdf; const doc = new jsPDF('landscape', 'mm', 'a4'); doc.setFontSize(14); doc.text(`Audit Report - ${type}`, 14, 15); doc.setFontSize(9); doc.text(`Generated By: ${currentUser.fullname} | Date: ${new Date().toLocaleString('id-ID')}`, 14, 21);
        const baseUrl = window.location.origin + '/'; let head = []; let body = [];
        if (type === 'GI') { head = [['Req ID/Date', 'ERP No', 'Requestor/Sec', 'Description', 'Item Code & Name', 'Qty/UoM', 'Total Price', 'RC / CC', 'App/Rcv Status']]; data.forEach(r => { r.items.forEach(i => { let st = `L1: ${r.app_head}\nWH: ${r.app_wh}`; if(r.received_by) st += `\nRCV: ${r.received_by}\n${r.receive_time}`; body.push([`${r.req_id}\n${r.created_at}`, r.erp_gi_no || '-', `${r.fullname}\n(${r.department} / ${r.section})`, r.purpose, `${i.code}\n${i.name}`, `${i.qty} ${i.uom}`, 'Rp ' + (parseFloat(i.price||0) * parseInt(i.qty)).toLocaleString('id-ID'), `${i.reason_code||'-'} / ${i.cost_center||'-'}`, st]); }); }); } else if (type === 'GR') { head = [['GR ID / Date', 'ERP No', 'Received By', 'Supplier/Remarks', 'Proof Link', 'Item Code & Name', 'Qty', 'UoM', 'Total Price']]; data.forEach(r => { const fGr = (r.gr_photo && r.gr_photo !== '0') ? baseUrl + r.gr_photo : '-'; r.items.forEach(i => { body.push([`${r.gr_id}\n${r.created_at}`, r.erp_gr_no || '-', r.fullname, r.remarks, fGr, `${i.code}\n${i.name}`, i.qty, i.uom, 'Rp ' + (parseFloat(i.price||0) * parseInt(i.qty)).toLocaleString('id-ID')]); }); }); } else if (type === 'INV') { head = [['Item Code', 'Item Name', 'Specification', 'Category', 'UoM', 'Stock', 'Price']]; data.forEach(r => { body.push([r.item_code, r.item_name, r.item_spec||'-', r.category, r.uom, r.stock, 'Rp ' + parseFloat(r.price||0).toLocaleString('id-ID')]); }); }
        doc.autoTable({ startY: 26, head: head, body: body, theme: 'grid', styles: { fontSize: 7, cellPadding: 2, overflow: 'linebreak' }, headStyles: { fillColor: [79, 70, 229] }}); doc.save(`GIS_Report_${type}_${new Date().getTime()}.pdf`);
    }

    function showDropdown(inp, type) { document.querySelectorAll('.dropdown-list').forEach(e => e.classList.add('hidden')); const list = inp.parentElement.querySelector('.dropdown-list'); list.classList.remove('hidden'); renderDropdownItems(inp, type); }
    function filterDropdown(inp, type) { renderDropdownItems(inp, type); }
    function renderDropdownItems(inp, type) {
        const list = inp.parentElement.querySelector('.dropdown-list'); const f = inp.value.toLowerCase();
        const filtered = inventoryData.filter(i => (i.item_code && i.item_code.toLowerCase().includes(f)) || (i.item_name && i.item_name.toLowerCase().includes(f)) || (i.item_spec && i.item_spec.toLowerCase().includes(f))).slice(0, 50);
        if(filtered.length === 0) { list.innerHTML = `<div class="p-2 text-[10px] text-slate-500 italic" data-translate="true">Tidak ada hasil</div>`; AutoTranslator.processDOM(); return; }
        let htmlArray = [];
        filtered.forEach(i => {
            const safeCode = (i.item_code||'').replace(/'/g, "\\'").replace(/"/g, '&quot;'); const safeName = (i.item_name||'').replace(/'/g, "\\'").replace(/"/g, '&quot;'); const safeSpec = (i.item_spec||'').replace(/'/g, "\\'").replace(/"/g, '&quot;');
            let specDisplay = i.item_spec ? ` - ${i.item_spec}` : '';
            htmlArray.push(`<div class="p-2 hover:bg-red-50 cursor-pointer text-[10px] border-b border-slate-50 transition-colors" onmousedown="selectItemOption(this, '${safeCode}', '${safeName}', '${safeSpec}', '${i.stock}', '${i.uom}', '${i.price || 0}', '${type}')"><div class="font-bold text-red-600">${i.item_code}</div><div class="text-slate-600">${i.item_name}${specDisplay} (Stk: ${i.stock})</div></div>`);
        });
        list.innerHTML = htmlArray.join(''); AutoTranslator.processDOM();
    }

    function selectItemOption(el, code, name, spec, stock, uom, price, type) {
        const row = el.closest(`div[id^="${type}-row-"]`); let displayVal = code + ' - ' + name; if(spec) displayVal += ' (' + spec + ')';
        row.querySelector(`.${type}-item-display`).value = displayVal; row.querySelector(`.${type}-item-code`).value = code; row.querySelector(`.${type}-item-name`).value = spec ? name + ' - ' + spec : name; row.querySelector(`.${type}-uom`).value = uom;
        const stockInput = row.querySelector(`.${type}-stock`); if(stockInput) stockInput.value = stock;

        if(type === 'gi') {
            const qtyInput = row.querySelector(`.${type}-qty`); qtyInput.max = stock; qtyInput.title = "Max stock: " + stock;
            const rawPriceEl = row.querySelector('.gi-price-raw'); const dispPriceEl = row.querySelector('.gi-price-display');
            if(rawPriceEl) rawPriceEl.value = price;
            if(dispPriceEl) dispPriceEl.value = 'Rp ' + parseFloat(price).toLocaleString('id-ID');
            if(typeof calculateGiTotal === 'function') calculateGiTotal();
        }
        if(type === 'gr') {
            const priceInput = row.querySelector('.gr-price');
            if(priceInput && (!priceInput.value || priceInput.value == 0)) { priceInput.value = price; }
            if(typeof calculateGrTotal === 'function') calculateGrTotal();
        }
        el.closest('.dropdown-list').classList.add('hidden');
    }
    document.addEventListener('click', function(e) { if(!e.target.closest('.relative.w-full')) { document.querySelectorAll('.dropdown-list').forEach(el => el.classList.add('hidden')); } });

    function compressImage(base64Str, maxWidth = 1000, quality = 0.6) {
        return new Promise((resolve, reject) => {
            const img = new Image(); img.src = base64Str;
            img.onload = () => {
                try {
                    const canvas = document.createElement('canvas'); let width = img.width; let height = img.height;
                    if (width > maxWidth) { height *= maxWidth / width; width = maxWidth; }
                    canvas.width = width; canvas.height = height; const ctx = canvas.getContext('2d'); ctx.drawImage(img, 0, 0, width, height);
                    resolve(canvas.toDataURL('image/jpeg', quality));
                } catch(e) { reject("Canvas error: " + e.message); }
            };
            img.onerror = () => resolve(base64Str);
        });
    }

    function openManageUsers() { openModal('modal-users'); loadUsers(); }
    function loadUsers() { fetch('api/users.php', {method:'POST', body:JSON.stringify({action:'getAllUsers'})}).then(r=>r.json()).then(d => { if(d.code===401){logoutAction(); return;} allUsers = Array.isArray(d) ? d : (d.data || []); renderUsers(allUsers); }); }
    function renderUsers(data) { const c = document.getElementById('user-list'); c.innerHTML = ''; let htmlArray = []; data.forEach(u => { htmlArray.push(`<div onclick="editUser('${u.username}')" class="p-2.5 sm:p-3 border border-slate-100 rounded-xl hover:bg-red-50 hover:border-red-200 cursor-pointer text-xs mb-2 transition shadow-sm bg-white"><div class="font-bold text-slate-700">${u.fullname}</div><div class="text-[10px] text-slate-500 mt-1.5">${u.username} • <span class="bg-slate-100 px-1.5 py-0.5 rounded font-bold">${u.role}</span></div></div>`); }); c.innerHTML = htmlArray.join(''); }
    function filterUsers() { const t = document.getElementById('search-user').value.toLowerCase(); renderUsers(allUsers.filter(u => u.fullname.toLowerCase().includes(t) || u.username.toLowerCase().includes(t))); }
    function handleRoleChange(sel) { if(sel.value === 'Administrator') { document.querySelectorAll('.acc-chk').forEach(chk => { chk.checked = true; chk.disabled = true; }); } else { document.querySelectorAll('.acc-chk').forEach(chk => { chk.disabled = false; }); } }
    function resetUserForm() { document.getElementById('user-form').reset(); document.getElementById('u-user').disabled=false; document.getElementById('u-pass').required=true; document.getElementById('btn-del-user').classList.add('hidden'); document.getElementById('form-title').innerText = 'Create User'; document.querySelectorAll('.acc-chk').forEach(chk => { chk.checked = false; chk.disabled = false; }); document.getElementById('chk-gi-submit').checked = true; AutoTranslator.processDOM(); }

    function editUser(u) {
        const user = allUsers.find(x => x.username === u);
        document.getElementById('u-user').value = user.username; document.getElementById('u-user').disabled=true;
        document.getElementById('u-pass').value = ''; document.getElementById('u-pass').required=false;
        document.getElementById('u-name').value = user.fullname; document.getElementById('u-nik').value = user.nik || '';
        document.getElementById('u-role').value = user.role; document.getElementById('u-dept').value = user.department;
        document.getElementById('u-phone').value = user.phone || ''; document.getElementById('btn-del-user').classList.remove('hidden');
        document.getElementById('form-title').innerText = 'Edit User';

        let acc = [];
        if (typeof user.access_rights === 'string') { try { acc = JSON.parse(user.access_rights); } catch (e) { acc = []; } } else if (Array.isArray(user.access_rights)) { acc = user.access_rights; }
        document.querySelectorAll('.acc-chk').forEach(chk => { chk.checked = acc.includes(chk.value); });
        if(user.role === 'Administrator') { document.querySelectorAll('.acc-chk').forEach(chk => { chk.checked = true; chk.disabled = true; }); } else { document.querySelectorAll('.acc-chk').forEach(chk => { chk.disabled = false; }); }
        AutoTranslator.processDOM();
    }

    function saveUser() {
        let acc = []; document.querySelectorAll('.acc-chk').forEach(chk => { if(chk.checked && !chk.disabled) acc.push(chk.value); });
        if (document.getElementById('u-role').value === 'Administrator') { acc = ['gi_submit', 'gr_submit', 'item_add', 'item_edit', 'stock_edit', 'export_data', 'price_add', 'price_edit', 'item_delete', 'edit_gi_no', 'edit_gr_no']; }
        const p = { action: 'saveUser', isEdit: document.getElementById('u-user').disabled, data: { username: document.getElementById('u-user').value, password: document.getElementById('u-pass').value, fullname: document.getElementById('u-name').value, nik: document.getElementById('u-nik').value, role: document.getElementById('u-role').value, department: document.getElementById('u-dept').value, phone: document.getElementById('u-phone').value, access_rights: JSON.stringify(acc) } };
        fetch('api/users.php', {method:'POST', body:JSON.stringify(p)}).then(r=>r.json()).then(res => { if(res.code === 401) { logoutAction(); return; } if(res.success){ resetUserForm(); loadUsers(); showCustomAlert("Success", "Berhasil simpan user."); } else showCustomAlert("Error", res.message); });
    }
    function deleteUser() { showCustomConfirm("Delete User", "Hapus user ini?", () => { fetch('api/users.php', {method:'POST', body:JSON.stringify({action:'deleteUser', username:document.getElementById('u-user').value})}).then(r=>r.json()).then(res => { if(res.success){ resetUserForm(); loadUsers(); } }); }); }
  </script>
</body>
</html>
