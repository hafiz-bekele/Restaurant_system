<script setup lang="ts">
import { onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useReservationStore, type TableOption } from '../../stores/reservation'
import { useUiStore } from '../../stores/ui'
import StepperHeader from './StepperHeader.vue'
import CustomerDetailsForm from './CustomerDetailsForm.vue'
import TableListScroller from './TableListScroller.vue'
import PriceSummaryCard from './PriceSummaryCard.vue'
import PaymentStatusPage from './PaymentStatusPage.vue'

const route = useRoute()
const router = useRouter()
const store = useReservationStore()
const ui = useUiStore()

onMounted(async () => {
  // Always load latest pricing rules from server
  await store.fetchPricingConfig()

  // Check if returning from Chapa with confirmation params
  const confirmationUlid = (route.query.ulid as string) || (route.query.confirmation as string)
  if (confirmationUlid) {
    store.step = 4
    if (!store.currentReservation || store.currentReservation.ulid !== confirmationUlid) {
      await store.fetchReservation(confirmationUlid).catch(() => {})
    }
    return
  }

  // If in step 2 and has inputs, re-fetch available tables
  if (store.step === 2 && store.canProceedToStep2) {
    store.fetchAvailableTables().catch(() => {})
  }
})

// Step 1: Customer Details Submitted -> Proceed to Step 2 & Fetch Tables
async function handleStep1Submit() {
  try {
    const tables = await store.fetchAvailableTables()
    store.step = 2
    window.scrollTo({ top: 0, behavior: 'smooth' })
    if (!tables.length) {
      ui.toast('No tables available for your chosen time. Try another slot or party size.', 'warning')
    }
  } catch (err: any) {
    ui.toast(err?.response?.data?.message || 'Could not fetch table availability.', 'error')
  }
}

// Step 2: Table Selected & Confirmed -> Proceed to Step 3
function handleStep2Select(table: TableOption) {
  store.selectTable(table)
}

function handleStep2Continue() {
  if (!store.selectedTable || !store.form.table_id) {
    ui.toast('Please choose a table before proceeding.', 'warning')
    return
  }
  store.step = 3
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

// Step 3: Direct Confirmation
async function handleStep3Pay() {
  if (!store.selectedTable || !store.form.table_id) {
    ui.toast('Please select a table first.', 'error')
    store.step = 2
    return
  }

  ui.startProcess('Securing your table reservation...')
  try {
    const data = await store.submitReservation()
    ui.toast('Table reservation confirmed successfully!', 'success')
    store.step = 4
    window.scrollTo({ top: 0, behavior: 'smooth' })
  } catch (err: any) {
    ui.toast(err?.response?.data?.message || 'Failed to confirm reservation.', 'error')
  } finally {
    ui.stopProcess()
  }
}

function handleRestart() {
  store.resetWizard()
  router.replace({ query: {} })
}
</script>

<template>
  <div class="reservation-wizard w-full max-w-4xl mx-auto space-y-8">
    <!-- Multi-step Stepper Header -->
    <StepperHeader
      :current-step="store.step"
      :can-go-to-step2="store.canProceedToStep2"
      :can-go-to-step3="store.canProceedToStep3"
      @step-click="store.goToStep"
    />

    <!-- Transition container for steps -->
    <transition
      name="fade-slide"
      mode="out-in"
    >
      <!-- STEP 1: Details & Time Selection -->
      <div v-if="store.step === 1" key="step-1" class="space-y-6">
        <CustomerDetailsForm
          @submit="handleStep1Submit"
        />
      </div>

      <!-- STEP 2: Choose Available Table -->
      <div v-else-if="store.step === 2" key="step-2" class="space-y-6">
        <div class="card-glass p-6 sm:p-8 rounded-3xl shadow-xl space-y-6">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-white/50 dark:border-white/10">
            <div>
              <span class="text-[11px] font-bold uppercase tracking-wider text-primary-600 dark:text-primary-400">
                Step 2 of 4
              </span>
              <h2 class="text-2xl font-bold font-heading text-warm-900 dark:text-white">
                Choose Your Table
              </h2>
              <p class="text-xs sm:text-sm text-warm-500 dark:text-warm-400 mt-0.5">
                Showing tables matching {{ store.form.party_size }} guests on {{ store.form.date }} at {{ store.form.start_time }}
              </p>
            </div>

            <button
              type="button"
              @click="store.step = 1"
              class="btn-outline text-xs self-start sm:self-auto py-1.5 px-3"
            >
              Change Date / Time
            </button>
          </div>

          <!-- Table list scroller with radio cards -->
          <TableListScroller
            :tables="store.availableTables"
            :selected-id="store.form.table_id"
            :loading="store.isLoadingTables"
            @select="handleStep2Select"
            @change-search="store.step = 1"
          />

          <!-- Action bar -->
          <div class="flex items-center justify-between pt-4 border-t border-white/50 dark:border-white/10">
            <button
              type="button"
              @click="store.step = 1"
              class="btn-outline text-sm"
            >
              Back
            </button>

            <button
              type="button"
              :disabled="!store.selectedTable || !store.form.table_id"
              @click="handleStep2Continue"
              class="btn-primary text-sm font-bold px-6 shadow-md"
            >
              Continue to Review & Pay
            </button>
          </div>
        </div>
      </div>

      <!-- STEP 3: Review and Pay -->
      <div v-else-if="store.step === 3" key="step-3" class="space-y-6">
        <PriceSummaryCard
          @back="store.step = 2"
          @pay="handleStep3Pay"
        />
      </div>

      <!-- STEP 4: Confirmation & Live Countdown -->
      <div v-else-if="store.step === 4" key="step-4" class="space-y-6">
        <PaymentStatusPage
          :ulid="store.currentReservation?.ulid || (route.query.ulid as string) || (route.query.confirmation as string)"
          @restart="handleRestart"
          @retry-payment="store.step = 3"
        />
      </div>
    </transition>
  </div>
</template>

<style scoped>
.fade-slide-enter-active,
.fade-slide-leave-active {
  transition: opacity 0.25s ease, transform 0.25s ease;
}

.fade-slide-enter-from {
  opacity: 0;
  transform: translateY(8px);
}

.fade-slide-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}
</style>
