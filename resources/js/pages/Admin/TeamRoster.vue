<script setup lang="ts">
import { Head } from '@inertiajs/vue3'

import ReadOnlyTeamRosterView from '@/components/team/ReadOnlyTeamRosterView.vue'
import BackLinkButton from '@/components/ui/BackLinkButton.vue'
import AdminDashboard from '@/pages/Admin/AdminDashboard.vue'

defineOptions({
    layout: AdminDashboard,
})

defineProps<{
    team: any | null
    readOnly: boolean
}>()
</script>

<template>
    <Head :title="team ? `${team.team_name} Roster` : 'Team Roster'" />

    <div class="space-y-6">
        <div class="flex justify-start">
            <BackLinkButton href="/teams" label="Back to Teams" />
        </div>

        <ReadOnlyTeamRosterView
            :team="team"
            hero-label="Admin Team View"
            hero-description="Monitor the assigned coaches, player roster, athlete status, and roster details in a unified read-only team view."
            empty-title="No team record available."
            empty-description="Select a valid team record to review its roster details."
        />
    </div>
</template>
