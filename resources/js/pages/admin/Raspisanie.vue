<script setup>


;
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';





const page = usePage();
const user = page.props.auth.user;



const pad = n => String(n).padStart(2,'0');
function hoursBetween(start,end){
    if(!start || !end) return 0;
    const [sh,sm]=start.split(':').map(Number), [eh,em]=end.split(':').map(Number);
    // eslint-disable-next-line prefer-const
    let a=sh*60+sm, b=eh*60+em;
    if(b<=a) b+=24*60;
    return Math.round((b-a)/60*10)/10;
}
function monthDays(year,month){ return new Date(year, month+1, 0).getDate(); }


        const ru = {
            months:['Январь','Февраль','Март','Апрель','Май','Июнь','Июль','Август','Сентябрь','Октябрь','Ноябрь','Декабрь'],
            monthsGen:['января','февраля','марта','апреля','мая','июня','июля','августа','сентября','октября','ноября','декабря'],
        };

        const today = new Date();
        const year  = ref(today.getFullYear());
        const month = ref(today.getMonth());

        // справочники
        const departments = ['Терапия','Хирургия','Кардиология','Эндокринология'];
        const roles = ['Медсестра','Постовая','Процедурная','Старшая смены'];

        // сотрудники (демо 10)
        const staff = ref([
            { id:'u1', name:'Иванова Н.В.',   role:'Медсестра',     department:'Терапия',        fte:1.0,  baseNorm:168 },
            { id:'u2', name:'Петрова А.С.',   role:'Постовая',      department:'Хирургия',       fte:0.75, baseNorm:168 },
            { id:'u3', name:'Ким Д.Р.',       role:'Процедурная',   department:'Кардиология',    fte:1.0,  baseNorm:168 },
            { id:'u4', name:'Соколова М.Е.',  role:'Медсестра',     department:'Хирургия',       fte:0.5,  baseNorm:168 },
            { id:'u5', name:'Орлова Т.И.',    role:'Постовая',      department:'Терапия',        fte:1.0,  baseNorm:168 },
            { id:'u6', name:'Семенова Я.В.',  role:'Медсестра',     department:'Эндокринология', fte:0.9,  baseNorm:168 },
            { id:'u7', name:'Романова К.О.',  role:'Старшая смены', department:'Кардиология',    fte:1.0,  baseNorm:168 },
            { id:'u8', name:'Кузнецова Л.С.', role:'Процедурная',   department:'Хирургия',       fte:0.75, baseNorm:168 },
            { id:'u9', name:'Васильева Е.П.', role:'Медсестра',     department:'Терапия',        fte:1.0,  baseNorm:168 },
            { id:'u10',name:'Гордеева А.М.',  role:'Постовая',      department:'Эндокринология', fte:0.6,  baseNorm:168 },
        ]);

        // смены
        const shifts = ref({}); // { [userId]: { [day]: {...} } }

        const filters = ref({ department:'', role:'' });
        const daysInMonth = computed(()=> monthDays(year.value, month.value));

        function rowCells(row){ if(!shifts.value[row.id]) shifts.value[row.id] = {}; return shifts.value[row.id]; }

        function defaultTimesByType(type){
            switch(type){
                case '8h':  return {start:'08:00', end:'16:00'};
                case '12h': return {start:'08:00', end:'20:00'};
                case '12n': return {start:'20:00', end:'08:00'};
                case '15h': return {start:'09:00', end:'24:00'};
                case '24h': return {start:'08:00', end:'08:00'};
                case 'home_day': return {start:'12:00', end:'24:00'};
                case 'home_night': return {start:'00:00', end:'08:00'};
                default: return {start:'', end:''};
            }
        }

        function shortLabel(row, day){
            const c = rowCells(row)[day]; if(!c || !c.type) return '';
            if(c.type==='12n') return '12ч (ночь)';
            if(c.type.startsWith('home')) return 'дом.';
            return c.type.replace('h','ч');
        }
        function timeLabel(row, day){ const c=rowCells(row)[day]; return c&&c.start?`${c.start}–${c.end}`:''; }

        function hoursForCell(c){ if(!c || !c.type) return 0; if(c.type==='24h') return 24; return hoursBetween(c.start,c.end); }
        function plannedFor(row){ const rc=rowCells(row); let s=0; for(let d=1; d<=daysInMonth.value; d++) s+=hoursForCell(rc[d]); return Math.round(s); }
        function normFor(row){ return Math.round(row.baseNorm * row.fte); }
        function balanceFor(row){ return normFor(row) - plannedFor(row); }

        function restConflict(row,day){
            const rc=rowCells(row), cur=rc[day]; if(!cur||!cur.type) return false;
            const prev=rc[day-1]; if(!prev||!prev.type) return false;
            const prevEnd=prev.end||defaultTimesByType(prev.type).end;
            const curStart=cur.start||defaultTimesByType(cur.type).start;
            const restH = (24 - hoursBetween(prev.start||'08:00', prevEnd)) + hoursBetween('00:00', curStart);
            return restH < 12;
        }
        function cellColor(row,day){
            const rc=rowCells(row)[day];
            if(!rc || !rc.type) return '';
            if(rc.type==='home_day' || rc.type==='home_night') return 'bg-amber-50 border-amber-200';
            if(restConflict(row,day)) return 'bg-rose-100 border-rose-300';
            return 'bg-mint-100 border-mint-300';
        }
        function mobileChipColor(row,day){
            const rc=rowCells(row)[day];
            if(!rc || !rc.type) return 'bg-white';
            if(rc.type==='home_day' || rc.type==='home_night') return 'bg-amber-50 border-amber-200';
            if(restConflict(row,day)) return 'bg-rose-100 border-rose-300';
            return 'bg-mint-50 border-mint-200';
        }

        const filteredRows = computed(()=> staff.value.filter(s=>{
            return (!filters.value.department || s.department===filters.value.department) &&
                (!filters.value.role || s.role===filters.value.role);
        }));

        // KPI (getters, чтобы обновлялись)
        const kpi = {};
        Object.defineProperty(kpi,'significance',{get(){return filteredRows.value.reduce((a,b)=>a+b.fte,0)}})
        Object.defineProperty(kpi,'norm',{get(){return filteredRows.value.reduce((a,r)=>a+normFor(r),0)}})
        Object.defineProperty(kpi,'planned',{get(){return filteredRows.value.reduce((a,r)=>a+plannedFor(r),0)}})
        Object.defineProperty(kpi,'balance',{get(){return kpi.norm - kpi.planned}})

        // ------- МОДАЛ --------
        const modal = ref({ open:false, row:null, day:null, form:{ type:'', start:'', end:'', post:'' } });
        function openCell(row, day){
            const rc = rowCells(row)[day] || {};
            modal.value.open = true;
            modal.value.row = row;
            modal.value.day = day;
            modal.value.form = { type: rc.type || '', start: rc.start || '', end: rc.end || '', post: rc.post || '' };
        }
        function saveCell(){
            const m = modal.value;
            const rc = rowCells(m.row);
            // eslint-disable-next-line prefer-const
            let {type,start,end,post} = m.form;
            if(type && (!start || !end)){ const d = defaultTimesByType(type); start = start||d.start; end=end||d.end; }
            rc[m.day] = type ? {type,start,end,post} : {};
            modal.value.open=false; toast('Изменения сохранены');
        }
        function clearCell(){ const m=modal.value; rowCells(m.row)[m.day] = {}; modal.value.open=false; toast('Ячейка очищена'); }

        // ------- ДЕМО-СИД --------
        function seedDemo(showToast){
            shifts.value = {};
            const totalDays = daysInMonth.value;

            staff.value.forEach((row, idx)=>{
                const rc = rowCells(row);
                let shiftToggle = idx % 2 === 0 ? 'day' : 'night'; // старт: часть людей с ночи

                for(let d=1; d<=totalDays; d++){
                    const dt = new Date(year.value, month.value, d);
                    const wd = dt.getDay(); // 0-вс
                    const weekIndex = Math.floor((d-1)/7); // 0..4

                    const workToday = (weekIndex % 2 === 0 ? (wd>=1 && wd<=5) : (wd>=1 && wd<=6)); // 5дн → 6дн → 5дн...
                    if(!workToday){ rc[d]={}; continue; }

                    // спец-вставки для реализма
                    if(idx===2 && (d===6 || d===7)){ rc[d]={ type:'home_day', ...defaultTimesByType('home_day'), post:'на дому' }; continue; }
                    if(idx===6 && d===15){ rc[d]={ type:'24h', ...defaultTimesByType('24h'), post:'дежурная' }; continue; }
                    if(idx===8 && (d===10 || d===11)){ rc[d]={ type:'8h', ...defaultTimesByType('8h'), post:'процедурная' }; continue; }

                    // основное чередование
                    if(shiftToggle==='day'){ rc[d]={ type:'12h', ...defaultTimesByType('12h'), post:'пост' }; shiftToggle='night'; }
                    else { rc[d]={ type:'12n', ...defaultTimesByType('12n'), post:'пост' }; shiftToggle='day'; }

                    // FTE < 1 — иногда выходной
                    if(row.fte < 1 && (d%9===0)) rc[d] = {};
                }

                // кардио/эндокрино — «домашние» слоты
                if(['Кардиология','Эндокринология'].includes(row.department)){
                    [3,18,27].forEach(dd=>{ if(dd<=totalDays) rc[dd] = { type:'home_day', ...defaultTimesByType('home_day'), post:'телефон' }; });
                    [4,19].forEach(dd=>{ if(dd<=totalDays) rc[dd] = { type:'home_night', ...defaultTimesByType('home_night'), post:'телефон' }; });
                }
            });

            if(showToast) toast('Демо-данные загружены');
        }

        // ------- ШАБЛОНЫ/ПАКЕТ --------
        function openTemplate(){
            filteredRows.value.forEach((row, idx)=>{
                for(let d=1; d<=daysInMonth.value; d++){
                    const wd = new Date(year.value, month.value, d).getDay();
                    if(wd===0){ rowCells(row)[d] = {}; continue; } // вс выходной
                    const isEven = ((d-1)+idx)%2===0;
                    rowCells(row)[d] = isEven ? { type:'12h', start:'08:00', end:'20:00', post:'пост' }
                        : { type:'12n', start:'20:00', end:'08:00', post:'пост' };
                }
            });
            toast('Шаблон применён');
        }
        function clearMonth(){ filteredRows.value.forEach(r=>shifts.value[r.id]={}); toast('Месяц очищен'); }
        function openCreateMany(){
            const startD=10;
            filteredRows.value.forEach(r=>{
                for(let d=startD; d<startD+3; d++){
                    rowCells(r)[d] = { type:'8h', start:'08:00', end:'16:00', post:'процедурная' };
                }
            });
            toast('Назначено пакетно (демо)');
        }

        // ------- НАВИГАЦИЯ -------
        function prevMonth(){ if(month.value===0){month.value=11; year.value--;} else month.value--; }
        function nextMonth(){ if(month.value===11){month.value=0; year.value++;} else month.value++; }

        // ------- DRAG-COPY ПО СТРОКЕ -------
        const drag = ref({ active:false, src:null });
        function startDrag(row,day,ev){ drag.value={active:true, src:{row,day}}; ev.currentTarget.classList.add('drag-hover'); }
        function dragOver(row,day,ev){
            if(!drag.value.active || !drag.value.src) return;
            if(row.id!==drag.value.src.row.id) return; // только в пределах строки
            ev.currentTarget.classList.add('drag-hover');
        }
        function leaveDrag(ev){ ev.currentTarget.classList.remove('drag-hover'); }
        function endDrag(row,day,ev){
            const src = drag.value.src;
            if(src && src.row.id===row.id){
                const s = rowCells(src.row)[src.day];
                if(s && s.type){ rowCells(row)[day] = { ...s }; }
            }
            drag.value={active:false, src:null};
            ev.currentTarget.classList.remove('drag-hover');
        }

        // ------- APPROVE/EXPORT MOCK -------
        function approveAll(){ toast('График утверждён (демо)'); }
        function exportMock(){ toast('Экспорт сформирован (демо)'); }

        // ------- TOAST -------
        const toasts = ref([]);
        function toast(text){ const id=Math.random().toString(36).slice(2); toasts.value.push({id,text}); setTimeout(()=>{ toasts.value = toasts.value.filter(t=>t.id!==id) }, 2400); }

        // авто-демо при загрузке
        onMounted(()=> seedDemo(false));




</script>

<template>
    <Head title="Welcome">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>



    <!-- TOPBAR -->
    <header class="from-brand-700 to-indigo-700 ">
        <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="size-15 rounded-xl bg-white/15 grid place-items-center">
                    <a href="/dashboard">
                    <img class="admin-doctor" src="/img/admin/doctor-admin.jpg" />
                    </a>
                </div>
                <div>
                    <div class="text-xs/4 opacity-80">Старшая медсестра: {{ user.name }}</div>
                    <div class="text-lg font-semibold">Панель планирования</div>
                    <div class="text-xs/4 opacity-80">Месяц: {{ ru.monthsGen[month] }} {{ year }}</div>
                </div>
            </div>
            <div class="hidden md:flex items-center gap-2">
                <button @click="approveAll" class="px-4 py-2 rounded-xl bg-white text-brand-700 font-medium shadow hover:bg-brand-50">Утвердить график</button>
                <button @click="exportMock" class="px-4 py-2 rounded-xl bg-white/10 ring-1 ring-white/30 hover:bg-white/20">Экспорт PDF/Excel</button>
            </div>
        </div>
    </header>

    <!-- CONTROLS -->
    <div class="max-w-7xl mx-auto w-full px-4 py-4 space-y-3 md:space-y-0 md:flex md:items-center md:justify-between">
        <div class="flex items-center gap-2">
            <button @click="prevMonth" class="px-3 py-2 rounded-lg border bg-white">◀</button>
            <button @click="nextMonth" class="px-3 py-2 rounded-lg border bg-white">▶</button>
            <select v-model.number="month" class="px-3 py-2 rounded-lg border bg-white">
                <option v-for="(m,idx) in ru.months" :value="idx">{{ m }}</option>
            </select>
            <input type="number" v-model.number="year" class="w-24 px-3 py-2 rounded-lg border bg-white" />
            <div class="hidden md:flex items-center gap-2 ml-4">
                <label class="text-sm text-slate-600">Отделение</label>
                <select v-model="filters.department" class="px-3 py-2 rounded-lg border bg-white">
                    <option value="">Все</option>
                    <option v-for="d in departments" :key="d" :value="d">{{ d }}</option>
                </select>
                <label class="text-sm text-slate-600 ml-2">Должность</label>
                <select v-model="filters.role" class="px-3 py-2 rounded-lg border bg-white">
                    <option value="">Все</option>
                    <option v-for="r in roles" :key="r" :value="r">{{ r }}</option>
                </select>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <button @click="seedDemo(true)" class="px-3 py-2 rounded-lg border bg-white">Загрузить демо</button>
            <button @click="openTemplate" class="px-3 py-2 rounded-lg border bg-white">Шаблон 1/2 смен</button>
            <button @click="clearMonth" class="px-3 py-2 rounded-lg border bg-white">Очистить месяц</button>
            <button @click="openCreateMany" class="px-3 py-2 rounded-lg border bg-white">Массовое назначение</button>
        </div>
    </div>

    <!-- KPI -->
    <div class="max-w-7xl mx-auto w-full px-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl p-4 shadow-soft border">
            <div class="text-sm text-slate-500">Объем значимости должности</div>
            <div class="text-2xl font-semibold">{{ kpi.significance.toFixed(2) }}</div>
            <div class="text-xs text-slate-500 mt-1">Сумма FTE по отфильтрованным сотрудникам</div>
        </div>
        <div class="bg-white rounded-2xl p-4 shadow-soft border">
            <div class="text-sm text-slate-500">Норма рабочего времени</div>
            <div class="text-2xl font-semibold">{{ kpi.norm }} ч</div>
            <div class="text-xs text-slate-500 mt-1">Сумма норм на месяц</div>
        </div>
        <div class="bg-white rounded-2xl p-4 shadow-soft border">
            <div class="text-sm text-slate-500">Планируемое время</div>
            <div class="text-2xl font-semibold">{{ kpi.planned }} ч</div>
            <div class="text-xs text-slate-500 mt-1">Назначено сменами</div>
        </div>
        <div class="bg-white rounded-2xl p-4 shadow-soft border">
            <div class="text-sm text-slate-500">Остаток / Переработка</div>
            <div class="text-2xl font-semibold" :class="kpi.balance>=0?'text-emerald-600':'text-rose-600'">{{ kpi.balance }} ч</div>
            <div class="text-xs text-slate-500 mt-1">Баланс = Норма − План</div>
        </div>
    </div>

    <!-- DESKTOP MATRIX -->
    <main class="max-w-7xl mx-auto w-full px-4 py-4 hidden lg:block">
        <div class="bg-white rounded-2xl border shadow-soft overflow-hidden">
            <div class="p-3 border-b flex items-center justify-between">
                <div class="text-sm text-slate-600">«Шахматка» — сотрудники × дни месяца</div>
                <div class="text-xs text-slate-500">Клик для редактирования; перетаскивайте по строке для копирования</div>
            </div>

            <div class="overflow-auto">
                <table class="min-w-full text-sm grid-sticky">
                    <thead>
                    <tr class="bg-slate-50 text-slate-600">
                        <th class="col-sticky left-0 min-w-[280px] text-left px-3 py-2 border-r">Сотрудник / Должность / FTE</th>
                        <th v-for="d in daysInMonth" :key="'h'+d" class="px-2 py-2 text-center border-b border-l min-w-[68px]">{{ d }}</th>
                        <th class="px-3 py-2 text-center border-b border-l min-w-[120px]">Итого (ч)</th>
                        <th class="px-3 py-2 text-center border-b border-l min-w-[140px]">Норма / Баланс</th>
                    </tr>
                    </thead>

                    <tbody>
                    <tr v-for="row in filteredRows" :key="row.id" class="odd:bg-white even:bg-slate-50/60">
                        <!-- sticky info -->
                        <td class="col-sticky left-0 border-r bg-white">
                            <div class="px-3 py-2">
                                <div class="font-medium">{{ row.name }}</div>
                                <div class="text-xs text-slate-500">{{ row.role }} • {{ row.department }}</div>
                                <div class="text-xs">FTE: <b>{{ row.fte }}</b> • Норма: <b>{{ normFor(row) }} ч</b></div>
                            </div>
                        </td>

                        <!-- cells -->
                        <td v-for="d in daysInMonth"
                            :key="row.id+'-'+d"
                            class="cell border-l border-b align-top cursor-pointer"
                            :class="cellColor(row, d)"
                            :data-day="d"
                            @mousedown="startDrag(row,d,$event)"
                            @mouseenter="dragOver(row,d,$event)"
                            @mouseup="endDrag(row,d,$event)"
                            @mouseleave="leaveDrag($event)"
                            @click="openCell(row,d)">
                            <div class="px-1.5 pt-1 leading-4 select-none">
                                <div class="font-medium">{{ shortLabel(row, d) }}</div>
                                <div class="text-[10px] text-slate-600">{{ timeLabel(row, d) }}</div>
                            </div>
                        </td>

                        <!-- totals -->
                        <td class="text-center border-l"><div class="px-2 py-2 font-semibold">{{ plannedFor(row) }}</div></td>
                        <td class="text-center border-l"><div class="px-2 py-2"><span :class="balanceFor(row)>=0 ? 'text-emerald-600' : 'text-rose-600'">{{ normFor(row) }} / {{ balanceFor(row) }}</span></div></td>
                    </tr>
                    </tbody>
                </table>
            </div>

            <div class="p-3 border-t text-xs text-slate-500">
                Цвета:
                <span class="inline-block w-3 h-3 bg-mint-100 border border-mint-300 align-middle mr-1"></span> назначено •
                <span class="inline-block w-3 h-3 bg-amber-100 border border-amber-300 align-middle mx-1"></span> домашнее дежурство •
                <span class="inline-block w-3 h-3 bg-rose-100 border border-rose-300 align-middle mx-1"></span> возможное нарушение отдыха
            </div>
        </div>
    </main>

    <!-- MOBILE -->
    <section class="lg:hidden px-4 py-4 space-y-3">
        <div class="flex gap-2">
            <select v-model="filters.department" class="flex-1 px-3 py-2 rounded-xl border bg-white">
                <option value="">Все отделения</option>
                <option v-for="d in departments" :value="d">{{ d }}</option>
            </select>
            <select v-model="filters.role" class="flex-1 px-3 py-2 rounded-xl border bg-white">
                <option value="">Все должности</option>
                <option v-for="r in roles" :value="r">{{ r }}</option>
            </select>
        </div>

        <div v-for="row in filteredRows" :key="'m'+row.id" class="bg-white rounded-2xl border shadow-soft overflow-hidden">
            <div class="p-3 flex items-start justify-between">
                <div>
                    <div class="font-medium">{{ row.name }}</div>
                    <div class="text-xs text-slate-500">{{ row.role }} • {{ row.department }}</div>
                    <div class="text-xs">Норма: <b>{{ normFor(row) }}</b> ч • План: <b>{{ plannedFor(row) }}</b> ч</div>
                </div>
                <div class="text-xs px-2 py-1 rounded-full"
                     :class="balanceFor(row)>=0?'bg-mint-50 text-emerald-700 border border-mint-200':'bg-rose-50 text-rose-700 border border-rose-200'">
                    Баланс: {{ balanceFor(row) }} ч
                </div>
            </div>
            <div class="p-3 pt-0">
                <div class="flex flex-wrap gap-2">
                    <button v-for="d in daysInMonth" :key="'mb'+row.id+'-'+d"
                            class="px-2.5 py-1.5 rounded-xl text-xs border"
                            :class="mobileChipColor(row,d)"
                            @click="openCell(row,d)">
                        {{ d }} • {{ shortLabel(row,d) || '—' }}
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- MODAL -->
    <div v-if="modal.open" class="fixed inset-0 z-50">
        <div class="absolute inset-0 bg-slate-900/50" @click="modal.open=false"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="w-full max-w-xl bg-white rounded-2xl shadow-soft border overflow-hidden">
                <div class="p-4 border-b flex items-center justify-between">
                    <div class="font-semibold">Назначение смены — {{ modal.row.name }} ({{ modal.day }} {{ ru.monthsGen[month] }})</div>
                    <button @click="modal.open=false" class="text-slate-500 hover:text-slate-800">✕</button>
                </div>
                <div class="p-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm text-slate-600">Тип смены</label>
                        <select v-model="modal.form.type" class="mt-1 w-full rounded-xl border px-3 py-2">
                            <option value="">— пусто —</option>
                            <option value="8h">8 часов</option>
                            <option value="12h">12 часов (08–20)</option>
                            <option value="12n">12 часов (20–08)</option>
                            <option value="15h">15 часов (09–24)</option>
                            <option value="24h">24 часа (08–08)</option>
                            <option value="home_day">Дежурство на дому (12–24)</option>
                            <option value="home_night">Дежурство на дому (00–08)</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm text-slate-600">Пост / место</label>
                        <input v-model="modal.form.post" placeholder="процедурная / пост 2" class="mt-1 w-full rounded-xl border px-3 py-2">
                    </div>
                    <div>
                        <label class="text-sm text-slate-600">Начало</label>
                        <input type="time" v-model="modal.form.start" class="mt-1 w-full rounded-xl border px-3 py-2">
                    </div>
                    <div>
                        <label class="text-sm text-slate-600">Окончание</label>
                        <input type="time" v-model="modal.form.end" class="mt-1 w-full rounded-xl border px-3 py-2">
                    </div>
                    <div class="sm:col-span-2 text-xs text-slate-500">
                        При выборе типа время подставится автоматически; «24 часа» — 08:00→08:00+1.
                    </div>
                </div>
                <div class="p-4 border-t flex items-center justify-between">
                    <button @click="clearCell" class="px-3 py-2 rounded-xl border">Очистить</button>
                    <div class="space-x-2">
                        <button @click="saveCell" class="px-4 py-2 rounded-xl bg-brand-600 text-white">Сохранить</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TOASTS -->
    <div class="fixed bottom-4 right-4 space-y-2 z-[60]">
        <div v-for="t in toasts" :key="t.id" class="bg-slate-900 text-white/95 rounded-xl px-4 py-3 shadow-soft">{{ t.text }}</div>
    </div>

    <footer class="py-8"></footer>

</template>


<style>
.grid-sticky th{ position: sticky; top: 0; z-index: 20; }
.col-sticky   { position: sticky; left: 0; z-index: 10; background: white;}
.drag-hover   { outline: 2px dashed rgba(99,102,241,.55); outline-offset: -2px; }
.cell{ min-width: 68px; height: 54px;}
.bg-mint-100{ background-color: #d1fae5; }
.bg-brand-600 { background-color: #4f46e5; }
.bg-brand-50:hover {background-color: #eef2ff;}
.bg-brand-50 {color: #eef2ff;}
.px-4 py-2 rounded-xl bg-white text-brand-700 font-medium shadow hover:bg-brand-50
{
    color: #eef2ff !important;
}
.admin-doctor
{
    border-radius: 25px;
}
</style>
