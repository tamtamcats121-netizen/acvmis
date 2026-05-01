<script setup lang="ts">
import { computed, ref } from 'vue'
import { VueCal } from 'vue-cal'

import { supportedSports, useSportColors } from '@/composables/useSportColors'
import AdminDashboard from '@/pages/Admin/AdminDashboard.vue'
import 'vue-cal/style'

defineOptions({
    layout: AdminDashboard,
})

const props = defineProps<{
    schedules: any[]
}>()

const { sportColor, sportTextColor, sportLabel } = useSportColors()

const selectedSport = ref('all')
const selectedTeam = ref('all')
const startDate = ref('')
const endDate = ref('')

const teamOptions = computed(() =>
    [...new Set((props.schedules || []).map((item: any) => item.team_name).filter(Boolean))]
        .sort((a, b) => String(a).localeCompare(String(b)))
)

const sportOptions = computed(() =>
    [...new Set((props.schedules || []).map((item: any) => sportLabel(item.sport)))]
        .filter((sport) => sport !== 'Unknown')
        .sort((a, b) => String(a).localeCompare(String(b)))
)

const sportsLegend = computed(() =>
    supportedSports.map((sport) => ({
        key: sport,
        label: sportLabel(sport),
        color: sportColor(sport),
    }))
)

const filteredSchedules = computed(() =>
    (props.schedules || []).filter((item: any) => {
        if (selectedSport.value !== 'all' && sportLabel(item.sport) !== selectedSport.value) return false
        if (selectedTeam.value !== 'all' && item.team_name !== selectedTeam.value) return false

        if (!startDate.value && !endDate.value) return true

        const eventStart = +new Date(item.start)
        const eventEnd = +new Date(item.end)
        if (Number.isNaN(eventStart) || Number.isNaN(eventEnd)) return false

        const rangeStart = startDate.value ? +new Date(`${startDate.value}T00:00:00`) : Number.NEGATIVE_INFINITY
        const rangeEnd = endDate.value ? +new Date(`${endDate.value}T23:59:59`) : Number.POSITIVE_INFINITY

        return eventEnd >= rangeStart && eventStart <= rangeEnd
    })
)

const sortedSchedules = computed(() =>
    [...filteredSchedules.value].sort((a, b) => +new Date(a.start) - +new Date(b.start))
)

const calendarEvents = computed(() =>
    sortedSchedules.value
        .filter(item => item.start && item.end)
        .map((item: any) => ({
            id: item.id,
            title: item.title,
            start: new Date(item.start),
            end: new Date(item.end),
            content: `${item.team_name || 'Unknown Team'} • ${item.type || ''}`,
            backgroundColor: sportColor(item.sport),
            color: sportTextColor(item.sport),
        }))
)

function formatPHT(dt: string | Date | null) {
    if (!dt) return '-'
    const d = typeof dt === 'string' ? new Date(dt) : dt

    return d.toLocaleString('en-PH', {
        timeZone: 'Asia/Manila',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    })
}
</script>

<template>
    <div class="space-y-4">
        <div>
            <h1 class="text-2xl font-bold text-white">Schedule Overview</h1>
            <p class="text-sm text-gray-400">All team schedules in one view.</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3">
            <select v-model="selectedSport" class="bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white">
                <option value="all">All Sports</option>
                <option v-for="sport in sportOptions" :key="sport" :value="sport">{{ sport }}</option>
            </select>
            <select v-model="selectedTeam" class="bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white">
                <option value="all">All Teams</option>
                <option v-for="team in teamOptions" :key="team" :value="team">{{ team }}</option>
            </select>
            <input v-model="startDate" type="date"
                class="bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white" />
            <input v-model="endDate" type="date"
                class="bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white" />
        </div>
        <div class="flex flex-wrap gap-4 text-xs">
            <div v-for="sport in sportsLegend" :key="sport.key" class="flex items-center gap-1 text-white">
                <span class="w-3 h-3 rounded" :style="{ backgroundColor: sport.color }"></span> {{ sport.label }}
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-4 gap-4">
            <section class="page-card xl:col-span-3 bg-gray-900 border border-gray-800 rounded-xl p-4">
                <VueCal sm style="height: 700px" :events="calendarEvents" default-view="week" :time="true"
                    :twelve-hour="true" time-format="h:mm {am}" events-on-month-view />
            </section>

            <aside class="page-card xl:col-span-1 bg-gray-900 border border-gray-800 rounded-xl p-4 max-h-175 overflow-y-auto">
                <h2 class="text-white font-semibold mb-3">Schedules</h2>

                <div v-if="sortedSchedules.length === 0" class="text-sm text-gray-500">
                    No schedules available.
                </div>

                <div v-else class="space-y-3">
                    <div v-for="item in sortedSchedules" :key="item.id" class="page-card rounded-lg border border-gray-800 bg-gray-950 p-3">
                        <div class="flex items-center justify-between gap-2">
                            <div class="text-white font-medium leading-tight">{{ item.title }}</div>
                            <span class="text-[10px] px-2 py-0.5 rounded font-semibold"
                                :style="{ backgroundColor: sportColor(item.sport), color: sportTextColor(item.sport) }">
                                {{ sportLabel(item.sport) }}
                            </span>
                        </div>
                        <div class="text-xs text-gray-400 mt-1">{{ item.team_name || 'Unknown Team' }} • {{ sportLabel(item.sport) }}</div>
                        <div class="text-xs text-gray-300 mt-2">{{ formatPHT(item.start) }}</div>
                        <div class="text-xs text-gray-300">{{ formatPHT(item.end) }}</div>
                        <div class="text-xs text-gray-500 mt-1">{{ item.venue || '-' }}</div>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</template>
