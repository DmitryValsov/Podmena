<script setup>
;
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';



const page = usePage();
//const user = page.props.auth.user;

/* Mock API */
const mockApi = {
    _delay(ms){ return new Promise(r=>setTimeout(r, ms)); },
    _fail(p=0.12){ return Math.random() < p; },
    async acceptRequest({id, idempotencyKey}){ await this._delay(450); if(this._fail()) throw new Error('Сервер недоступен'); return { id, status:'accepted', idempotencyKey }; },
    async declineRequest({id, idempotencyKey}){ await this._delay(280); if(this._fail(0.08)) throw new Error('Ошибка сети'); return { id, status:'declined', idempotencyKey }; },
    async createSwap(payload){ await this._delay(420); if(this._fail(0.1)) throw new Error('Не удалось создать заявку'); return { id: cryptoId(), ...payload, status:'await_colleagues' }; }
};

/* Utils */
const pad = n => String(n).padStart(2,'0');
const toISO = d => d.toISOString().slice(0,10);
const fromISO = s => { const [y,m,d]=s.split('-').map(Number); return new Date(y,m-1,d); }
const addDays = (d,n)=>{ const x=new Date(d); x.setDate(x.getDate()+n); return x; }
const fmtTime = d => pad(d.getHours())+':'+pad(d.getMinutes());
const hexWithAlpha=(hex,a)=>{const h=hex.replace('#','');const r=parseInt(h.slice(0,2),16),g=parseInt(h.slice(2,4),16),b=parseInt(h.slice(4,6),16);return`rgba(${r},${g},${b},${a})`}
function cryptoId(){ return Math.random().toString(36).slice(2); }
function watchValue(src, cb){ let cur=src.value; setInterval(()=>{ if(src.value!==cur){ cur=src.value; cb(cur);} }, 120); }


        const me = { id:'me', name:'Анна Петрова', department:'Хирургия', avatar:'https://i.pravatar.cc/100?img=48' };
        const colleagues = ref([
            { id:'c1', name:'Екатерина И.', department:'Терапия',   avatar:'https://i.pravatar.cc/100?img=30', color:'#90E696' },
            { id:'c2', name:'Мария С.',     department:'Приёмное',  avatar:'https://i.pravatar.cc/100?img=31', color:'#80D2F9' },
            { id:'c3', name:'Динара К.',    department:'Хирургия',  avatar:'https://i.pravatar.cc/100?img=32', color:'#FEA8BF' },
            { id:'c4', name:'Ольга Л.',     department:'Кардиология',avatar:'https://i.pravatar.cc/100?img=33', color:'#FEC30B' },
        ]);
        const usersWithMe = ref([{...me, color:'#F15780'}, ...colleagues.value]);

        const today = new Date();
        const selectedMobile = ref(toISO(today));
        const monthLabel = computed(()=> fromISO(selectedMobile.value).toLocaleDateString('ru-RU',{month:'long'}).replace(/^./,c=>c.toUpperCase()));
        function daysRange(anchor){ const out=[]; for(let i=-2;i<=4;i++){ const d=addDays(anchor,i); out.push({ key:d.toDateString(), date:toISO(d), day:d.getDate(), weekday:['Пн','Вт','Ср','Чт','Пт','Сб','Вс'][(d.getDay()+6)%7], isSelected:toISO(d)===selectedMobile.value }); } return out; }
        const days = ref(daysRange(today));
        function selectMobile(iso){ selectedMobile.value=iso; days.value=daysRange(fromISO(iso)); }
        function shiftMobile(n){ selectMobile(toISO(addDays(fromISO(selectedMobile.value),n))); }
        function goToday(){ selectMobile(toISO(today)); selectedDesktop.value=toISO(today); }

        const hours = ref(['13:00','14:00','15:00','16:00','17:00','18:00','19:00','20:00','21:00']);
        const scope = ref('mine');

        const weekStart = ref(startOfWeek(today));
        const weekDays = computed(()=>{
            const arr=[]; const names=['Пн','Вт','Ср','Чт','Пт','Сб','Вс'];
            for(let i=0;i<7;i++){ const d=addDays(weekStart.value,i);
                arr.push({ date:d, key:d.toDateString(), weekday:names[i], label:`${pad(d.getDate())}.${pad(d.getMonth()+1)}`, isToday:toISO(d)===toISO(today) })
            } return arr;
        });
        const gridStartHour = ref(8);
        const weekSlots = computed(()=>{ const arr=[]; for(let h=gridStartHour.value; h<=21; h++) arr.push(pad(h)+':00'); return arr; });
        const rangeLabel = computed(()=>{ const s=weekStart.value, e=addDays(s,6); return `${pad(s.getDate())}.${pad(s.getMonth()+1)}–${pad(e.getDate())}.${pad(e.getMonth()+1)}.${e.getFullYear()}`; });
        function weekShift(n){ weekStart.value=addDays(weekStart.value,n*7); desktopRoster.value = genWeek(weekStart.value, usersWithMe.value); }
        function startOfWeek(d){ const x=new Date(d); const day=(x.getDay()+6)%7; x.setDate(x.getDate()-day); x.setHours(0,0,0,0); return x; }
        const selectedDesktop = ref(toISO(today));
        function selectDayDesktop(d){ selectedDesktop.value = toISO(d); }

        const mobileRoster = ref(genDay(selectedMobile.value, usersWithMe.value));
        const desktopRoster = ref(genWeek(weekStart.value, usersWithMe.value));
        const swaps = ref(genSwaps(desktopRoster.value, usersWithMe.value));
        const flt = ref({ kind24:true, kind12day:true, kind12night:true, onlyMine:false, onlyVacant:false });
        const search = ref('');
        watchValue(selectedMobile, iso => mobileRoster.value = genDay(iso, usersWithMe.value));

        const hourPx = ref(64);
        onMounted(() => { updateNowLine(); setInterval(updateNowLine, 60*1000); });
        const nowTopPx = ref(null);
        function updateNowLine(){
            const now = new Date();
            const gridStartMin = gridStartHour.value*60;
            const last = parseInt(weekSlots.value.at(-1).slice(0,2),10)+1;
            const gridEndMin = last*60;
            const nowMin = now.getHours()*60 + now.getMinutes();
            if (nowMin<gridStartMin || nowMin>gridEndMin) { nowTopPx.value=null; return; }
            nowTopPx.value = (nowMin - gridStartMin) / 60 * hourPx.value;
        }

        function eventsForDay(date){
            const ds = toISO(date);
            const list = desktopRoster.value.filter(i => i.date===ds);
            const gridStartMin = gridStartHour.value * 60;
            const last = parseInt(weekSlots.value.at(-1).slice(0,2),10)+1;
            const gridEndMin   = last * 60;
            return list
                .filter(i=>{
                    if(i.kind==='24h' && !flt.value.kind24) return false;
                    if(i.kind==='12h' && i.part==='day' && !flt.value.kind12day) return false;
                    if(i.kind==='12h' && i.part==='night' && !flt.value.kind12night) return false;
                    if(flt.value.onlyMine && !i.nurses.some(n=>n.id===me.id)) return false;
                    if(flt.value.onlyVacant && i.nurses.length>=i.capacity) return false;
                    if(search.value){ const q=search.value.toLowerCase(); if(!i.nurses.some(n=>n.name.toLowerCase().includes(q))) return false; }
                    return true;
                })
                .map(i=>{
                    const startMin = i.start_at.getHours()*60 + i.start_at.getMinutes();
                    const endMin   = i.end_at.getHours()*60   + i.end_at.getMinutes();
                    const clippedStart = Math.max(startMin, gridStartMin);
                    const clippedEnd   = Math.min(endMin,   gridEndMin);
                    if (clippedEnd <= clippedStart) return null;
                    const topPx    = (clippedStart - gridStartMin) / 60 * hourPx.value;
                    const heightPx = (clippedEnd   - clippedStart) / 60 * hourPx.value;
                    const color = i.nurses[0]?.color || '#D1D5DB';
                    const bg = hexWithAlpha(color, 0.18);
                    const border = color;
                    const time = `${fmtTime(i.start_at)}–${fmtTime(i.end_at)}`;
                    const kindLabel = i.kind==='24h' ? '24 часа' : (i.part==='day'?'12ч (день)':'12ч (ночь)');
                    const swapCount = swaps.value.filter(s=>s.shift_id===i.id && s.status!=='declined').length;
                    return { id:i.id, top:`${topPx}px`, height:`${heightPx}px`, bg, border, title:`Смена • ${i.nurses.length} мед.`, time, kindLabel, place:i.place, assigned:i.nurses.length, capacity:i.capacity, swapCount, isMine: i.nurses.some(n=>n.id===me.id) }
                }).filter(Boolean);
        }

        function eventsAt(hour){
            return mobileRoster.value
                .filter(e => e.date===selectedMobile.value && e.start.slice(0,2)===hour.slice(0,2))
                .filter(e => scope.value==='mine' ? e.isMine : true)
                .map(e => ({ id:e.id, title:e.title, place:e.place, line: e.line, time: e.start+'–'+e.end, kindLabel: e.kind==='24h'?'24 часа':(e.part==='day'?'12ч (день)':'12ч (ночь)'), assigned:e.assigned, capacity:e.capacity, fill:e.assigned/e.capacity, alert: e.assigned>=e.capacity ? 'мест нет' : (e.assigned/e.capacity>=0.8 ? 'почти заполнено' : ''), isMine: e.isMine }));
        }
        const mineCount = computed(()=> mobileRoster.value.filter(e=>e.date===selectedMobile.value && e.isMine).length);
        const deptCount = computed(()=> mobileRoster.value.filter(e=>e.date===selectedMobile.value).length);

        const colleaguesRequests = ref(seedRequests());
        const requestsScope = ref('day');
        const visibleRequestsMobile = computed(()=>{
            return colleaguesRequests.value.filter(r => r.status==='pending').filter(r => r.dateISO === selectedMobile.value).filter(r => r.type==='all' || r.targetId==='me').map(addDateLabel);
        });
        const visibleRequestsDesktop = computed(()=>{
            const start = requestsScope.value==='day' ? fromISO(selectedDesktop.value): weekStart.value;
            const end   = requestsScope.value==='day' ? addDays(start,1) : addDays(weekStart.value,7);
            return colleaguesRequests.value.filter(r => r.status==='pending').filter(r => { const d=fromISO(r.dateISO); return d>=start && d<end; }).filter(r => r.type==='all' || r.targetId==='me').map(addDateLabel);
        });
        function addDateLabel(r){ return { ...r, dateLabel: new Date(r.dateISO).toLocaleDateString('ru-RU',{day:'2-digit',month:'long'}) } }

        async function acceptRequest(rq){ if(rq.busy || rq.status!=='pending') return; const prev = rq.status; const key = cryptoId(); rq.busy = true; rq.status = 'accepted'; try{ await mockApi.acceptRequest({id:rq.id, idempotencyKey:key}); toast(`Отклик отправлен: ${rq.name}`); }catch(e){ rq.status = prev; toast('Ошибка: ' + e.message); }finally{ rq.busy = false; } }
        async function declineRequest(rq){ if(rq.busy || rq.status!=='pending') return; const prev = rq.status; const key = cryptoId(); rq.busy = true; rq.status = 'declined'; try{ await mockApi.declineRequest({id:rq.id, idempotencyKey:key}); toast('Отказ отправлен'); }catch(e){ rq.status = prev; toast('Ошибка: ' + e.message); }finally{ rq.busy = false; } }

        const minDate = toISO(today);
        const quick = ref({ date: toISO(today), shift:'12h', note:'', targetType:'all', search:'', targetUser:null });
        const quickBusy = ref(false);
        const requests = ref([]);
        function selectDirect(c){ quick.value.targetUser=c; directSearch.value=[]; quick.value.search=''; }
        async function submitQuick(){ if(quick.value.targetType==='direct' && !quick.value.targetUser) return toast('Выберите адресата'); quickBusy.value = true; try{ const payload = { date: quick.value.date, shift:quick.value.shift, note:quick.value.note, targetType: quick.value.targetType, targetUser: quick.value.targetUser }; const res = await mockApi.createSwap(payload); requests.value.push({ id:res.id, ...payload, status:'await_colleagues', offers:[] }); toast(quick.value.targetType==='all' ? 'Общая заявка отправлена' : `Личная → ${quick.value.targetUser.name}`); quick.value.note=''; quick.value.targetUser=null; quick.value.search=''; quick.value.targetType='all'; }catch(e){ toast('Ошибка создания заявки: '+e.message); }finally{ quickBusy.value = false; } }

        const requestsSorted = computed(()=> [...requests.value].sort((a,b)=> a.date.localeCompare(b.date)));
        function demoAddOffer(r){ r.offers=(r.offers||[]).concat({by:'Екатерина И.'}); toast('Пришёл отклик'); }
        function sendToHead(r){ if(!r.offers?.length) return toast('Нет откликов'); r.status='await_head'; toast('Отправлено старшей'); }
        function approveByHead(r){ r.status='approved'; toast('Подмена утверждена'); }
        function revertRequest(r){ r.status='await_colleagues'; toast('Заявка возвращена'); }
        function clearAllRequests(){ requests.value=[]; }

        function badgeClass(s){ return s==='await_colleagues'?'bg-amber-100 text-amber-800 border border-amber-200':s==='await_head'?'bg-indigo-100 text-indigo-800 border-indigo-200':'bg-emerald-100 text-emerald-800 border-emerald-200'; }
        function statusText(s){ return s==='await_colleagues'?'Ждёт коллег':s==='await_head'?'Ждёт старшую':'Утверждена'; }
        function shiftLabel(v){ return v==='12h'?'12 часов':'24 часа'; }
        function fmt(s){ const d=fromISO(s); return `${pad(d.getDate())}.${pad(d.getMonth()+1)}.${d.getFullYear()}`; }

        const swap = ref({ open:false, ev:null, direct:false, search:'', target:null, busy:false });
        function openSwap(ev){ swap.value={ open:true, ev, direct:false, search:'', target:null, busy:false }; }
        const modalSearch = ref([]), directSearch = ref([]);
        function filterColleagues(src){ const q = (src==='modal'? swap.value.search : quick.value.search).toLowerCase().trim(); const list = !q ? [] : colleagues.value.filter(c=> (c.name+' '+c.department).toLowerCase().includes(q)); (src==='modal'? modalSearch : directSearch).value = list; }
        async function sendSwap(type){ if(type==='direct' && !swap.value.target) return toast('Выберите адресата'); swap.value.busy = true; try{ await mockApi.createSwap({ type, to: type==='direct'? swap.value.target.id : null, from: me.id }); toast(type==='all' ? 'Отправлена общая заявка' : `Личная → ${swap.value.target.name}`); swap.value.open=false; }catch(e){ toast('Ошибка: '+e.message); }finally{ swap.value.busy = false; } }

        const stats = ref({ given:3, taken:1 });
        const nextShift = ref({ date: toISO(addDays(today,2)), shift:'12h', department:'Хирургия' });
        const toasts = ref([]);
        function toast(text){ const id=cryptoId(); toasts.value.push({id,text}); setTimeout(()=> toasts.value = toasts.value.filter(t=>t.id!==id), 2600); }

        function isToday(d){ return toISO(d)===toISO(today); }
        function isSameISO(isoOrStr,d2){ const a=typeof isoOrStr==='string'?isoOrStr:toISO(isoOrStr); return a===toISO(d2); }



/* DEMO generators */
/* DEMO generators */
function genDay(iso, users){
    const mk=(h1,m1,h2,m2,title,color,isMine=false)=>({
        id: cryptoId(),
        date: iso,
        start: `${pad(h1)}:${pad(m1)}`,
        end: `${pad(h2)}:${pad(m2)}`,
        title,
        place: 'пост 1',
        kind:'12h',
        part: h1<19?'day':'night',
        assigned: Math.floor(Math.random()*6)+2,
        capacity: 8,
        isMine,
        line: color
    });
    return [
        mk(17,15,18,10,'Перевязки', '#90E696', true),
        mk(18,15,19,10,'Приём пациентов', '#80D2F9'),
        mk(19,15,20,10,'Палатный обход', '#FEA8BF'),
        mk(20,15,21,10,'Дежурство поста', '#FEC30B', true),
    ];
}
function genWeek(weekStart, users){
    const out=[]; let id=1;
    for(let i=0;i<7;i++){
        const day = addDays(weekStart,i); const iso=toISO(day);
        const use24 = Math.random()<0.35;
        const places=['пост 1','пост 2','процедурная','перевязочная'];
        if(use24){
            const [sa,ea]=span(day,'08:00','08:00+1');
            out.push({ id:id++, date:iso, kind:'24h', part:null, start_at:sa, end_at:ea, capacity:rint(1,3), place:places[rint(0,places.length-1)], nurses: pick(users,rint(1,3)) });
        }else{
            const [s1,e1]=span(day,'08:00','20:00');
            const [s2,e2]=span(day,'20:00','08:00+1');
            out.push({ id:id++, date:iso, kind:'12h', part:'day', start_at:s1, end_at:e1, capacity:rint(1,3), place:places[rint(0,places.length-1)], nurses: pick(users,rint(1,3)) });
            out.push({ id:id++, date:iso, kind:'12h', part:'night', start_at:s2, end_at:e2, capacity:rint(1,3), place:places[rint(0,places.length-1)], nurses: pick(users,rint(1,3)) });
        }
    }
    return out;
}
function span(d, start, end){
    const mk = s => {
        const plus = s.includes('+1');
        const [hh,mm] = s.replace('+1','').split(':').map(Number);
        const x = new Date(d.getFullYear(), d.getMonth(), d.getDate(), hh, mm||0);
        if(plus) x.setDate(x.getDate()+1);
        return x;
    };
    return [ mk(start), mk(end) ];
}
function pick(arr,n){ const a=[...arr]; const out=[]; while(n>0 && a.length){ out.push(a.splice(Math.floor(Math.random()*a.length),1)[0]); n--; } return out; }
function rint(a,b){ return Math.floor(Math.random()*(b-a+1))+a; }
function genSwaps(instances, users){
    const reqs=[]; const cand=[...instances].sort(()=>0.5-Math.random()).slice(0,3);
    for(const inst of cand){
        const requester = inst.nurses[0];
        if(!requester) continue;
        const type = Math.random()<0.5 ? 'all' : 'direct';
        const target = type==='direct' ? pick(users.filter(u=>u.id!==requester.id),1)[0] : null;
        reqs.push({ id:'sr'+inst.id, shift_id:inst.id, requester, type, target, status:'await_colleagues' });
    }
    return reqs;
}
function seedRequests(){
    const plus = (n)=> toISO(addDays(new Date(), n));
    return [
        { id:'rq1', name:'Екатерина И.', avatar:'https://i.pravatar.cc/100?img=30', type:'all',    targetId:null, dateISO: plus(0), time:'17:15–18:10', kindLabel:'12ч (день)', note:'Семейные обстоятельства', status:'pending', busy:false },
        { id:'rq2', name:'Мария С.',     avatar:'https://i.pravatar.cc/100?img=31', type:'direct', targetId:'me',  dateISO: plus(0), time:'19:15–20:10', kindLabel:'12ч (ночь)', note:'Обмен сменами',         status:'pending', busy:false },
        { id:'rq3', name:'Динара К.',    avatar:'https://i.pravatar.cc/100?img=32', type:'all',    targetId:null, dateISO: plus(1), time:'08:00–20:00', kindLabel:'24 часа',    note:'Учёба',                 status:'pending', busy:false },
        { id:'rq4', name:'Ольга Л.',     avatar:'https://i.pravatar.cc/100?img=33', type:'direct', targetId:'me',  dateISO: plus(3), time:'08:00–20:00', kindLabel:'12ч (день)', note:'Перенос смены',         status:'pending', busy:false },
    ];
}
</script>

<template>
    <Head title="Welcome">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>



        <!-- HEADER -->
        <header class="bg-white/95 backdrop-blur border-b sticky top-0 z-30">
            <div class="max-w-7xl mx-auto px-4 py-3 flex items-center gap-3">
                <img :src="me.avatar" class="w-10 h-10 rounded-full ring-2 ring-slate-100" alt="">
                <div>
                    <div class="text-sm text-slate-500">Личный кабинет</div>
                    <div class="font-semibold">{{ me.name }} • {{ me.department }}</div>
                </div>
                <div class="ml-auto hidden md:flex items-center gap-2">
                    <button @click="openSwap(null)" class="btn rounded-lg border px-3 py-2 hover:bg-slate-50">Мне нужна подмена</button>
                    <button @click="goToday" class="btn rounded-lg border px-3 py-2 bg-indigo-50 text-brand-600">Сегодня</button>
                </div>
            </div>

            <!-- MOBILE controls -->
            <div class="md:hidden px-4 pb-3">
                <div class="flex items-center gap-2">
                    <button @click="shiftMobile(-1)" class="rounded-full border w-8 h-8">‹</button>
                    <div class="font-semibold">{{ monthLabel }}</div>
                    <button @click="shiftMobile(1)" class="rounded-full border w-8 h-8">›</button>
                    <button @click="goToday" class="ml-auto text-xs rounded-full border px-3 py-1 bg-indigo-50 text-brand-600">Сегодня</button>
                </div>
                <div class="mt-2 flex gap-2 overflow-x-auto no-scrollbar">
                    <button v-for="d in days" :key="d.key" @click="selectMobile(d.date)"
                            class="min-w-[56px] py-2 rounded-2xl border flex flex-col items-center"
                            :class="d.isSelected ? 'bg-brand-600 text-white border-brand-600' : 'bg-slate-100 text-slate-700 border-slate-200'">
                        <div class="text-xs">{{ d.weekday }}</div>
                        <div class="text-base font-semibold">{{ d.day }}</div>
                    </button>
                </div>
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <button @click="scope='mine'"
                            :class="scope==='mine' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700'"
                            class="rounded-full px-3 py-1.5 text-sm">Мои {{ mineCount }}</button>
                    <button @click="scope='dept'"
                            :class="scope==='dept' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700'"
                            class="rounded-full px-3 py-1.5 text-sm">Отделение {{ deptCount }}</button>
                </div>
            </div>

            <!-- DESKTOP controls -->
            <div class="hidden md:block border-t">
                <div class="max-w-7xl mx-auto px-4 py-3 flex flex-wrap items-center gap-3">
                    <div class="flex items-center gap-2">
                        <button @click="weekShift(-1)" class="btn rounded-md border px-3 py-2">◀</button>
                        <div class="font-semibold">{{ rangeLabel }}</div>
                        <button @click="weekShift(1)" class="btn rounded-md border px-3 py-2">▶</button>
                    </div>
                    <div class="flex-1"></div>
                    <div class="flex gap-3 items-center text-sm">
                        <label class="inline-flex items-center gap-2"><input type="checkbox" v-model="flt.kind24"> 24ч</label>
                        <label class="inline-flex items-center gap-2"><input type="checkbox" v-model="flt.kind12day"> 12ч (день)</label>
                        <label class="inline-flex items-center gap-2"><input type="checkbox" v-model="flt.kind12night"> 12ч (ночь)</label>
                        <label class="inline-flex items-center gap-2"><input type="checkbox" v-model="flt.onlyMine"> Только мои</label>
                        <label class="inline-flex items-center gap-2"><input type="checkbox" v-model="flt.onlyVacant"> Незаполненные</label>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="text-xs text-slate-500">Старт: {{ gridStartHour }}:00</div>
                        <input type="range" min="6" max="12" v-model.number="gridStartHour" class="w-28" />
                        <div class="text-xs text-slate-500">Зум (px/час): {{ hourPx }}</div>
                        <input type="range" min="48" max="96" step="4" v-model.number="hourPx" class="w-28" />
                    </div>
                    <div class="relative">
                        <input v-model="search" type="text" placeholder="Поиск по имени" class="rounded-md border px-3 py-2 pl-9">
                        <svg class="absolute left-2 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="7" stroke-width="2"/><path d="m20 20-3.5-3.5" stroke-width="2" stroke-linecap="round"/></svg>
                    </div>
                </div>
            </div>
        </header>

        <!-- CONTENT -->
        <main class="flex-1">
            <div class="max-w-7xl mx-auto md:grid md:grid-cols-3 gap-4 px-4">

                <!-- MOBILE TIMELINE -->
                <section class="md:hidden col-span-2">
                    <div v-for="hour in hours" :key="hour" class="mt-2">
                        <div class="text-xs text-slate-400 pl-1">{{ hour }}</div>
                        <div class="space-y-2 mt-1">
                            <div v-for="ev in eventsAt(hour)" :key="ev.id" class="rounded-xl border bg-white px-3 py-2 shadow-sm flex gap-2 items-start">
                                <div class="w-1.5 h-12 rounded" :style="{background: ev.line}"></div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <div class="font-medium truncate" :class="ev.isMine?'text-slate-900':'text-slate-700'">{{ ev.title }}</div>
                                        <span class="text-[11px] text-slate-500">· {{ ev.place }}</span>
                                    </div>
                                    <div class="text-xs text-slate-500 mt-0.5">{{ ev.time }}</div>
                                    <div class="text-xs mt-1 flex items-center gap-3">
                                        <span class="inline-flex items-center gap-1 text-slate-600">🩺 {{ ev.kindLabel }}</span>
                                        <span class="inline-flex items-center gap-1"
                                              :class="ev.fill>=1 ? 'text-rose-600' : ev.fill>=0.8 ? 'text-amber-600' : 'text-emerald-600'">
                        👥 {{ ev.assigned }} / {{ ev.capacity }}
                  </span>
                                        <span v-if="ev.alert" class="text-amber-700">⚠ {{ ev.alert }}</span>
                                    </div>
                                </div>
                                <button v-if="ev.isMine" @click="openSwap(ev)"
                                        class="btn text-xs rounded-lg border px-2 py-1 hover:bg-slate-50">Подмена</button>
                            </div>
                        </div>
                    </div>

                    <!-- Mobile: colleague requests -->
                    <div class="mt-4 rounded-xl border bg-white p-3 shadow-sm">
                        <div class="flex items-center justify-between">
                            <h3 class="font-semibold">Запросы коллег на подмену</h3>
                            <span class="text-xs text-slate-500">общие + адресные</span>
                        </div>
                        <div class="mt-2 divide-y">
                            <div v-for="rq in visibleRequestsMobile" :key="rq.id" class="py-2 flex gap-3 items-start">
                                <img :src="rq.avatar" class="w-8 h-8 rounded-full" />
                                <div class="flex-1 min-w-0">
                                    <div class="font-medium flex items-center gap-2">
                                        {{ rq.name }}
                                        <span class="text-[10px] border rounded px-1.5"
                                              :class="rq.type==='all' ? 'bg-slate-50' : 'bg-indigo-50 text-brand-600 border-indigo-200'">
                    {{ rq.type==='all'?'общая':'личная' }}
                  </span>
                                    </div>
                                    <div class="text-xs text-slate-500">{{ rq.dateLabel }} • {{ rq.kindLabel }} • {{ rq.time }}</div>
                                    <div v-if="rq.note" class="text-sm mt-1">{{ rq.note }}</div>
                                    <div class="mt-2 flex gap-2">
                                        <button @click="acceptRequest(rq)" :disabled="rq.busy || rq.status!=='pending'"
                                                class="btn rounded bg-emerald-600 text-white px-3 py-1.5 text-sm flex items-center gap-2">
                                            <span v-if="rq.busy" class="spinner"></span><span>Подменить</span>
                                        </button>
                                        <button @click="declineRequest(rq)" :disabled="rq.busy || rq.status!=='pending'"
                                                class="btn rounded border px-3 py-1.5 text-sm flex items-center gap-2">
                                            <span v-if="rq.busy" class="spinner spinner-dark"></span><span>Отказаться</span>
                                        </button>
                                    </div>
                                    <div v-if="rq.status!=='pending'" class="mt-1 text-xs"
                                         :class="rq.status==='accepted' ? 'text-emerald-700' : 'text-slate-500'">
                                        {{ rq.status==='accepted' ? 'Вы откликнулись. Ждёт подтверждения.' : 'Вы отказались.' }}
                                    </div>
                                </div>
                            </div>
                            <div v-if="!visibleRequestsMobile.length" class="py-3 text-sm text-slate-500">На выбранную дату заявок нет.</div>
                        </div>
                    </div>
                </section>

                <!-- DESKTOP WEEK GRID -->
                <section class="hidden md:block md:col-span-2 py-4">
                    <div class="border rounded-xl bg-white overflow-hidden shadow-soft relative">
                        <div class="grid grid-cols-8 border-b bg-slate-50">
                            <div class="p-3 text-xs text-slate-500">Время</div>
                            <div v-for="d in weekDays" :key="d.key" class="p-3 text-center cursor-pointer" @click="selectDayDesktop(d.date)">
                                <div class="text-xs text-slate-500">{{ d.weekday }}</div>
                                <div class="font-medium" :class="isSameISO(selectedDesktop, d.date) ? 'text-brand-600 underline' : (d.isToday ? 'text-brand-600' : '')">{{ d.label }}</div>
                            </div>
                        </div>
                        <div class="grid grid-cols-8">
                            <div class="border-r bg-slate-50">
                                <div v-for="t in weekSlots" :key="t" class="grid-hour px-2 text-xs text-slate-500 flex items-start pt-1" :style="{height: hourPx+'px'}">{{ t }}</div>
                            </div>
                            <div v-for="d in weekDays" :key="d.key" class="relative border-l last:border-r">
                                <!-- hour lines -->
                                <div class="pointer-events-none">
                                    <div v-for="t in weekSlots" :key="t" class="grid-hour" :style="{height: hourPx+'px'}"></div>
                                </div>

                                <!-- Now line -->
                                <div v-if="isToday(d.date) && nowTopPx!==null" class="now-line" :style="{ top: nowTopPx+'px' }"></div>

                                <!-- Events -->
                                <div class="absolute inset-0">
                                    <div v-for="ev in eventsForDay(d.date)" :key="ev.id"
                                         class="absolute left-1 right-1 event-card bg-white border rounded-xl px-2 py-1 text-xs"
                                         :style="{ top: ev.top, height: ev.height, backgroundColor: ev.bg, borderColor: ev.border }">
                                        <div class="flex items-center gap-2">
                                            <span class="inline-block w-1.5 h-4 rounded" :style="{background: ev.border}"></span>
                                            <div class="font-medium truncate" :class="ev.isMine ? 'text-slate-900' : 'text-slate-700'">{{ ev.title }}</div>
                                            <button v-if="ev.isMine" @click="openSwap(ev)" class="ml-auto text-[11px] rounded border px-2 py-0.5">Подмена</button>
                                        </div>
                                        <div class="text-[11px] text-slate-600 mt-0.5 flex flex-wrap items-center gap-2">
                                            <span>{{ ev.time }}</span>
                                            <span>• {{ ev.kindLabel }}</span>
                                            <span>• Пост: {{ ev.place }}</span>
                                            <span>• {{ ev.assigned }} / {{ ev.capacity }}</span>
                                            <span v-if="ev.swapCount" class="inline-flex items-center gap-1 text-amber-700 bg-amber-50 border border-amber-200 rounded px-1">🔁 {{ ev.swapCount }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3 text-xs text-slate-600 flex flex-wrap gap-4">
                        <div class="inline-flex items-center gap-2"><span class="w-3 h-3 rounded" style="background:#80D2F9"></span> Смена</div>
                        <div>🔁 — есть заявки на подмену</div>
                    </div>
                </section>

                <!-- SIDEBAR -->
                <aside class="py-4 space-y-4 md:col-span-1">
                    <!-- Requests from colleagues (DESKTOP) -->
                    <div class="bg-white border rounded-xl shadow-sm">
                        <div class="px-4 py-3 border-b flex items-center justify-between">
                            <h3 class="font-semibold">Запросы коллег</h3>
                            <div class="text-xs flex gap-1">
                                <button @click="requestsScope='day'"
                                        :class="requestsScope==='day'?'bg-slate-900 text-white':'bg-slate-100'"
                                        class="rounded-full px-2 py-1">за день</button>
                                <button @click="requestsScope='week'"
                                        :class="requestsScope==='week'?'bg-slate-900 text-white':'bg-slate-100'"
                                        class="rounded-full px-2 py-1">за неделю</button>
                            </div>
                        </div>
                        <div class="max-h-[360px] overflow-auto divide-y">
                            <div v-for="rq in visibleRequestsDesktop" :key="rq.id" class="p-3 flex items-start gap-3">
                                <img :src="rq.avatar" class="w-8 h-8 rounded-full"/>
                                <div class="flex-1 min-w-0">
                                    <div class="font-medium flex items-center gap-2">
                                        {{ rq.name }}
                                        <span class="text-[10px] px-1.5 py-0.5 rounded border"
                                              :class="rq.type==='all' ? 'bg-slate-50 text-slate-700 border-slate-200' : 'bg-indigo-50 text-indigo-700 border-indigo-200'">
                    {{ rq.type==='all' ? 'общая' : 'личная' }}
                  </span>
                                    </div>
                                    <div class="text-xs text-slate-500 truncate">{{ rq.dateLabel }} • {{ rq.kindLabel }} • {{ rq.time }}</div>
                                    <div class="text-xs mt-1" v-if="rq.note">{{ rq.note }}</div>
                                    <div class="mt-2 flex gap-2">
                                        <button @click="acceptRequest(rq)" :disabled="rq.busy || rq.status!=='pending'"
                                                class="btn rounded bg-emerald-600 text-white px-3 py-1.5 text-xs flex items-center gap-2">
                                            <span v-if="rq.busy" class="spinner"></span><span>Подменить</span>
                                        </button>
                                        <button @click="declineRequest(rq)" :disabled="rq.busy || rq.status!=='pending'"
                                                class="btn rounded border px-3 py-1.5 text-xs flex items-center gap-2">
                                            <span v-if="rq.busy" class="spinner spinner-dark"></span><span>Отказаться</span>
                                        </button>
                                    </div>
                                    <div v-if="rq.status!=='pending'" class="mt-1 text-xs"
                                         :class="rq.status==='accepted' ? 'text-emerald-700' : 'text-slate-500'">
                                        {{ rq.status==='accepted' ? 'Вы откликнулись. Ждёт подтверждения.' : 'Вы отказались.' }}
                                    </div>
                                </div>
                            </div>
                            <div v-if="!visibleRequestsDesktop.length" class="p-4 text-sm text-slate-500">Заявок нет.</div>
                        </div>
                    </div>

                    <!-- Quick swap -->
                    <div class="bg-white border rounded-xl p-4 shadow-sm">
                        <h3 class="font-semibold mb-2">Быстрая подмена</h3>
                        <div class="text-xs text-slate-600 border rounded-xl bg-slate-50 px-3 py-2 mb-3">
                            Создайте <b>общую</b> заявку (видят все) или <b>личную</b> (видит только выбранная медсестра и старшая).
                        </div>
                        <div class="space-y-3">
                            <div>
                                <label class="text-sm text-slate-600">Дата</label>
                                <input type="date" v-model="quick.date" :min="minDate" class="mt-1 w-full rounded-xl border px-3 py-2">
                            </div>
                            <div>
                                <label class="text-sm text-slate-600">Смена</label>
                                <select v-model="quick.shift" class="mt-1 w-full rounded-xl border px-3 py-2">
                                    <option value="12h">12 часов</option>
                                    <option value="24h">24 часа</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-sm text-slate-600">Тип заявки</label>
                                <div class="grid grid-cols-2 gap-2 mt-1">
                                    <button @click="quick.targetType='all'" :class="quick.targetType==='all'?'bg-brand-600 text-white':'bg-white'" class="btn rounded-xl px-3 py-2 border">Общая</button>
                                    <button @click="quick.targetType='direct'" :class="quick.targetType==='direct'?'bg-brand-600 text-white':'bg-white'" class="btn rounded-xl px-3 py-2 border">Личная</button>
                                </div>
                            </div>
                            <div v-if="quick.targetType==='direct'">
                                <label class="text-sm text-slate-600">Медсестра</label>
                                <div class="relative mt-1">
                                    <input v-model="quick.search" @input="filterColleagues('quick')" placeholder="Начните вводить имя" class="w-full rounded-xl border px-3 py-2">
                                    <div v-if="directSearch.length" class="absolute z-10 mt-1 w-full bg-white border rounded-xl max-h-48 overflow-auto shadow-soft">
                                        <button v-for="c in directSearch" :key="c.id" @click="selectDirect(c)" class="w-full text-left px-3 py-2 hover:bg-slate-50 flex items-center gap-2">
                                            <img :src="c.avatar" class="w-5 h-5 rounded-full"> <span>{{ c.name }} • {{ c.department }}</span>
                                        </button>
                                    </div>
                                </div>
                                <div v-if="quick.targetUser" class="mt-1 text-xs text-slate-600">Адресат: <b>{{ quick.targetUser.name }}</b></div>
                            </div>
                            <div>
                                <label class="text-sm text-slate-600">Комментарий</label>
                                <textarea v-model="quick.note" rows="2" class="mt-1 w-full rounded-xl border px-3 py-2"></textarea>
                            </div>
                            <button @click="submitQuick" class="btn w-full rounded-xl bg-brand-600 text-white py-2.5 flex justify-center gap-2">
                                <span v-if="quickBusy" class="spinner"></span><span>Отправить заявку</span>
                            </button>
                        </div>
                    </div>

                    <!-- My requests -->
                    <div class="bg-white border rounded-xl shadow-sm">
                        <div class="px-4 py-3 border-b flex items-center justify-between">
                            <h3 class="font-semibold">Мои заявки</h3>
                            <button @click="clearAllRequests" class="text-xs text-slate-500 hover:text-red-600">очистить</button>
                        </div>
                        <div class="max-h-[360px] overflow-auto divide-y">
                            <div v-if="!requestsSorted.length" class="p-4 text-sm text-slate-500">Пока нет заявок.</div>
                            <div v-for="r in requestsSorted" :key="r.id" class="p-3 flex items-start gap-3">
                                <div class="mt-1">
                                    <div :class="badgeClass(r.status)" class="text-[10px] px-1.5 py-0.5 rounded font-semibold uppercase tracking-wide">
                                        {{ statusText(r.status) }}
                                    </div>
                                </div>
                                <div class="flex-1">
                                    <div class="font-medium flex items-center gap-2">
                                        {{ fmt(r.date) }} — {{ shiftLabel(r.shift) }}
                                        <span class="text-[10px] px-1.5 py-0.5 rounded border"
                                              :class="r.targetType==='all' ? 'bg-slate-50' : 'bg-indigo-50 text-indigo-700 border-indigo-200'">
                    {{ r.targetType==='all' ? 'общая' : 'личная' }}
                  </span>
                                        <span v-if="r.targetType==='direct' && r.targetUser" class="text-xs text-slate-500">→ {{ r.targetUser.name }}</span>
                                    </div>
                                    <div class="text-xs text-slate-500" v-if="r.note">{{ r.note }}</div>
                                    <div class="mt-2 flex flex-wrap gap-2 text-xs">
                                        <span v-if="r.offers?.length" class="inline-flex items-center gap-1 rounded bg-emerald-50 text-emerald-700 px-2 py-1 border border-emerald-200">Откликов: {{ r.offers.length }}</span>
                                        <button v-if="r.status==='await_colleagues'" @click="demoAddOffer(r)" class="btn rounded border px-2 py-1 hover:bg-slate-50">Демо: отклик</button>
                                        <button v-if="r.status==='await_colleagues' && r.offers?.length" @click="sendToHead(r)" class="btn rounded bg-amber-50 text-amber-700 px-2 py-1 border border-amber-200">Отправить старшей</button>
                                        <button v-if="r.status==='await_head'" @click="approveByHead(r)" class="btn rounded bg-emerald-600 text-white px-2 py-1">Демо: утвердить</button>
                                        <button v-if="r.status==='approved'" @click="revertRequest(r)" class="btn rounded bg-slate-100 px-2 py-1">Отменить</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-white border rounded-xl p-4 shadow-sm">
                            <div class="text-sm text-slate-500">Баланс подмен</div>
                            <div class="text-2xl font-semibold">{{ stats.given }} / {{ stats.taken }}</div>
                            <div class="text-xs text-slate-500">Отдал(а) / Взял(а)</div>
                        </div>
                        <div class="bg-white border rounded-xl p-4 shadow-sm">
                            <div class="text-sm text-slate-500">Ближайшая смена</div>
                            <div class="font-medium">{{ fmt(nextShift.date) }} — {{ shiftLabel(nextShift.shift) }}</div>
                            <div class="text-xs text-slate-500">Отделение: {{ nextShift.department }}</div>
                        </div>
                    </div>
                </aside>

            </div>
        </main>

        <!-- Bottom nav (mobile) -->
        <nav class="md:hidden sticky bottom-0 left-0 right-0 bg-white border-t">
            <div class="max-w-7xl mx-auto grid grid-cols-4 text-center text-xs py-2">
                <button class="text-brand-600">📅<div>Расписание</div></button>
                <button class="opacity-60">🤝<div>Подмены</div></button>
                <button class="opacity-60">📝<div>Задачи</div></button>
                <button class="opacity-60">👤<div>Профиль</div></button>
            </div>
        </nav>

        <!-- Swap modal -->
        <div v-if="swap.open" class="fixed inset-0 z-50">
            <div class="absolute inset-0 bg-slate-900/50" @click="swap.open=false"></div>
            <div class="absolute inset-0 flex items-end md:items-center justify-center p-3">
                <div class="w-full max-w-md bg-white rounded-2xl p-4 shadow-xl">
                    <div class="font-semibold">Заявка на подмену</div>
                    <div class="text-sm text-slate-600 mt-1" v-if="swap.ev">{{ swap.ev.title }} — {{ swap.ev.time }}</div>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <button @click="sendSwap('all')" class="btn rounded-full px-3 py-2 bg-slate-900 text-white flex justify-center gap-2">
                            <span v-if="swap.busy" class="spinner"></span><span>Общая</span>
                        </button>
                        <button @click="swap.direct=true" class="btn rounded-full px-3 py-2 border">Личная</button>
                    </div>
                    <div v-if="swap.direct" class="mt-3">
                        <input v-model="swap.search" @input="filterColleagues('modal')" class="w-full border rounded-xl px-3 py-2" placeholder="Выберите медсестру" />
                        <div v-if="modalSearch.length" class="mt-1 border rounded-xl max-h-40 overflow-auto">
                            <button v-for="c in modalSearch" :key="c.id" class="w-full text-left px-3 py-2 hover:bg-slate-50"
                                    @click="swap.target=c; modalSearch=[]">{{ c.name }} • {{ c.department }}</button>
                        </div>
                        <div v-if="swap.target" class="text-xs text-slate-600 mt-1">Адресат: <b>{{ swap.target.name }}</b></div>
                        <button @click="sendSwap('direct')" class="mt-2 btn rounded-full px-3 py-2 bg-brand-600 text-white w-full flex justify-center gap-2">
                            <span v-if="swap.busy" class="spinner"></span><span>Отправить личную</span>
                        </button>
                    </div>
                    <button class="mt-3 btn w-full rounded-full px-3 py-2 border" @click="swap.open=false">Отмена</button>
                </div>
            </div>
        </div>

        <!-- Toasts -->
        <div class="fixed bottom-4 right-4 space-y-2 z-[60]">
            <div v-for="t in toasts" :key="t.id" class="bg-slate-900 text-white/95 rounded-xl px-4 py-3 shadow-soft">{{ t.text }}</div>
        </div>


</template>


<style>
.no-scrollbar::-webkit-scrollbar{display:none}
.grid-hour{position:relative}
.grid-hour::after{content:""; position:absolute; inset-inline:0; bottom:0; height:1px; background:rgba(15,23,42,.08)}
.event-card{box-shadow:0 12px 24px -18px rgba(2,6,23,.28)}
.now-line{position:absolute; left:0; right:0; height:2px; background:#ef4444}
.btn[disabled]{opacity:.5; cursor:not-allowed}
.spinner{width:14px;height:14px;border-radius:50%;border:2px solid rgba(255,255,255,.6);border-top-color:#fff;animation:spin .8s linear infinite}
.spinner-dark{border-color:rgba(15,23,42,.4);border-top-color:#0f172a}
@keyframes spin{to{transform:rotate(360deg)}}
</style>
