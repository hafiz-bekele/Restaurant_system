<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import { RouterLink } from 'vue-router'
import {
  ArrowRight, Star, Clock, MapPin, QrCode, ChefHat, Truck,
  CalendarCheck, BedDouble, Award, ShieldCheck, Smile, Phone,
  UtensilsCrossed, Leaf, Flame, Heart, ChevronLeft, ChevronRight,
  Play, CheckCircle,
} from '@lucide/vue'
import DefaultLayout from '../layouts/DefaultLayout.vue'
import { menuApi, guestApi } from '../services/api'
import DishCard from '../components/menu/DishCard.vue'

// ── Data queries ─────────────────────────────────────────────────────────────
const { data: categoriesData } = useQuery({
  queryKey: ['categories'],
  queryFn:  () => menuApi.categories().then(r => r.data.data),
  staleTime: 3600000,
})

const { data: featuredData, isLoading: featuredLoading } = useQuery({
  queryKey: ['dishes', 'featured'],
  queryFn:  () => menuApi.featured().then(r => r.data.data),
  staleTime: 900000,
})

// ── Animated counter ─────────────────────────────────────────────────────────
const statsVisible   = ref(false)
const statsRef       = ref(null)
const animatedValues = ref([0, 0, 0, 0])

const statsTargets = [
  { target: 98,  suffix: '%', label: 'Guest Satisfaction' },
  { target: 12,  suffix: '+', label: 'Years of Excellence' },
  { target: 150, suffix: '+', label: 'Menu Selections' },
  { target: 24,  suffix: '/7', label: 'Room Service' },
]

function animateCounters() {
  statsTargets.forEach((stat, i) => {
    const duration = 1800
    const steps    = 60
    const step     = stat.target / steps
    let   current  = 0
    const timer = setInterval(() => {
      current = Math.min(current + step, stat.target)
      animatedValues.value[i] = Math.floor(current)
      if (current >= stat.target) clearInterval(timer)
    }, duration / steps)
  })
}

let statsObserver
onMounted(() => {
  statsObserver = new IntersectionObserver(
    ([entry]) => { if (entry.isIntersecting && !statsVisible.value) { statsVisible.value = true; animateCounters() } },
    { threshold: 0.3 }
  )
  if (statsRef.value) statsObserver.observe(statsRef.value)
})
onUnmounted(() => statsObserver?.disconnect())

// ── Testimonials auto-carousel ────────────────────────────────────────────────
const testimonials = [
  { name: 'Abebe K.',  role: 'Regular Guest',     text: 'The QR ordering is so convenient — I placed my order before even sitting down. Food arrived hot and fresh.', stars: 5 },
  { name: 'Sara M.',   role: 'Business Traveler',  text: 'Room service charged straight to my bill. No hassle at all. Exactly how a hotel restaurant should work.', stars: 5 },
  { name: 'Dawit T.',  role: 'Food Enthusiast',    text: 'Great variety on the menu and the dietary filters made it easy to find exactly what I was looking for.', stars: 5 },
  { name: 'Helen A.',  role: 'Family Diner',       text: 'Brought the whole family for a birthday dinner. The staff were attentive and the food was absolutely delicious.', stars: 5 },
  { name: 'Yonas B.',  role: 'Hotel Guest',        text: 'Checked in late and room service was ready in under 30 minutes. Impressed by the speed and quality.', stars: 5 },
]
const activeTestimonial = ref(0)
let testimonialTimer
function startTestimonialTimer() {
  testimonialTimer = setInterval(() => {
    activeTestimonial.value = (activeTestimonial.value + 1) % testimonials.length
  }, 4500)
}
function prevTestimonial() {
  clearInterval(testimonialTimer)
  activeTestimonial.value = (activeTestimonial.value - 1 + testimonials.length) % testimonials.length
  startTestimonialTimer()
}
function nextTestimonial() {
  clearInterval(testimonialTimer)
  activeTestimonial.value = (activeTestimonial.value + 1) % testimonials.length
  startTestimonialTimer()
}
onMounted(startTestimonialTimer)
onUnmounted(() => clearInterval(testimonialTimer))

// ── Static content ────────────────────────────────────────────────────────────
const howItWorks = [
  { icon: QrCode,           step: '01', title: 'Scan or Browse',   desc: 'Scan the table QR code or browse our menu online. No login required.' },
  { icon: UtensilsCrossed,  step: '02', title: 'Build Your Order',  desc: 'Choose your dishes, customise with extras, add special notes.' },
  { icon: ChefHat,          step: '03', title: 'Kitchen Prepares',  desc: 'Our chefs receive your order instantly and begin preparing it fresh.' },
  { icon: Truck,            step: '04', title: 'Enjoy Your Meal',   desc: 'Your order is delivered to your table or door in record time.' },
]

const features = [
  { icon: QrCode,         title: 'QR Ordering',         desc: 'Scan any table or room QR to order instantly — no app, no login.' },
  { icon: BedDouble,      title: 'Room Service',         desc: 'Hotel guests can charge orders directly to their room folio.' },
  { icon: CalendarCheck,  title: 'Table Reservations',   desc: 'Book your table in seconds and arrive to a perfectly set experience.' },
  { icon: ShieldCheck,    title: 'Secure Payments',      desc: 'Pay with Chapa, Telebirr, card, or cash — all transactions encrypted.' },
  { icon: Leaf,           title: 'Dietary Filters',      desc: 'Easily filter vegan, vegetarian, halal, and allergen-free options.' },
  { icon: Smile,          title: 'Live Order Tracking',  desc: 'Watch your order move from kitchen to table in real time.' },
]

const dietaryBadges = [
  { icon: Leaf,  label: 'Vegan Options' },
  { icon: Flame, label: 'Spice Levels'  },
  { icon: Heart, label: 'Allergen Info' },
  { icon: Award, label: 'Chef Specials' },
]

const highlights = [
  'Fresh ingredients sourced daily',
  'Authentic Ethiopian & international cuisine',
  'Fully accessible dining spaces',
  'Private event & banquet facilities',
]
</script>

<template>
  <DefaultLayout>

    <!-- ══ HERO ══════════════════════════════════════════════════════════════ -->
    <section class="relative overflow-hidden min-h-screen flex items-center">
      <div class="absolute inset-0">
        <img
          src="https://images.unsplash.com/photo-1414235077428-338989a2e8c0?w=1920&q=80"
          alt="Fine dining atmosphere"
          class="w-full h-full object-cover scale-105 transition-transform duration-[8000ms]"
          style="animation: heroZoom 12s ease-in-out infinite alternate"
        />
        <div class="absolute inset-0 bg-gradient-to-r from-black/90 via-black/65 to-black/20"></div>
        <!-- Decorative vertical line -->
        <div class="absolute left-0 top-0 h-full w-1 bg-gradient-to-b from-transparent via-primary-500 to-transparent opacity-60"></div>
      </div>

      <div class="page-container relative z-10 py-24">
        <div class="max-w-2xl">
          <!-- Eyebrow -->
          <div class="flex items-center gap-3 mb-7">
            <div class="h-px w-10 bg-primary-400"></div>
            <span class="text-primary-400 text-sm font-semibold uppercase tracking-[0.2em]">Fine Dining & Hospitality</span>
          </div>

          <h1 class="font-heading text-5xl md:text-6xl lg:text-[4.5rem] font-bold leading-[1.08] text-white mb-7">
            Where Every<br>
            <span class="relative inline-block">
              <span class="text-primary-400">Meal Becomes</span>
            </span><br>
            a Memory
          </h1>

          <p class="text-lg text-white/70 mb-10 max-w-xl leading-relaxed">
            Crafted with the finest ingredients, served with care. Experience world-class dining, seamless ordering, and premium hotel hospitality — all in one place.
          </p>

          <!-- Highlights list -->
          <ul class="space-y-2 mb-10">
            <li v-for="h in highlights" :key="h" class="flex items-center gap-2.5 text-white/80 text-sm">
              <CheckCircle :size="16" class="text-primary-400 shrink-0" />
              {{ h }}
            </li>
          </ul>

          <!-- CTAs -->
          <div class="flex flex-wrap gap-4">
            <RouterLink to="/menu" class="group btn-primary text-base px-8 py-3.5 shadow-lg shadow-primary-500/40 hover:shadow-primary-500/60 hover:scale-[1.02] transition-all duration-200">
              Explore Menu <ArrowRight :size="18" class="group-hover:translate-x-1 transition-transform" />
            </RouterLink>
            <RouterLink to="/reservations" class="group inline-flex items-center gap-2 rounded-xl px-8 py-3.5 text-base font-semibold border-2 border-white/30 text-white hover:border-primary-400 hover:bg-white/8 transition-all duration-200">
              <CalendarCheck :size="18" class="text-primary-400" /> Reserve a Table
            </RouterLink>
          </div>
        </div>
      </div>

      <!-- Floating info card -->
      <div class="absolute bottom-10 right-8 hidden xl:block">
        <div class="glass rounded-2xl p-5 flex gap-4 items-center shadow-float max-w-[17rem] border border-white/10">
          <div class="w-12 h-12 rounded-xl bg-primary-500/20 border border-primary-500/30 flex items-center justify-center shrink-0">
            <QrCode :size="24" class="text-primary-400" />
          </div>
          <div>
            <p class="font-semibold text-white text-sm leading-tight">Instant QR Ordering</p>
            <p class="text-xs text-white/50 mt-0.5">Scan any table — no app needed.</p>
          </div>
        </div>
        <!-- Scroll indicator -->
        <div class="mt-6 flex flex-col items-center gap-1 opacity-50">
          <div class="w-px h-8 bg-white/40 rounded-full"></div>
          <span class="text-[10px] text-white/40 uppercase tracking-widest">Scroll</span>
        </div>
      </div>
    </section>

    <!-- ══ DIETARY TICKER ════════════════════════════════════════════════════ -->
    <section class="bg-primary-600 dark:bg-primary-700 py-3.5 overflow-hidden">
      <div class="page-container">
        <div class="flex flex-wrap items-center justify-center gap-10">
          <div v-for="item in dietaryBadges" :key="item.label" class="flex items-center gap-2 text-white/95">
            <component :is="item.icon" :size="15" />
            <span class="text-sm font-semibold tracking-wide">{{ item.label }}</span>
          </div>
        </div>
      </div>
    </section>

    <!-- ══ ANIMATED STATS ════════════════════════════════════════════════════ -->
    <section ref="statsRef" class="py-16 bg-warm-950 dark:bg-gray-950">
      <div class="page-container">
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-6">
          <div
            v-for="(stat, i) in statsTargets"
            :key="stat.label"
            class="text-center group"
          >
            <div class="inline-flex items-end gap-0.5 mb-2">
              <span class="font-heading text-4xl md:text-5xl font-bold text-primary-400 tabular-nums leading-none">
                {{ statsVisible ? animatedValues[i] : 0 }}
              </span>
              <span class="font-heading text-2xl font-bold text-primary-300 mb-1">{{ stat.suffix }}</span>
            </div>
            <p class="text-sm text-warm-400 font-medium tracking-wide">{{ stat.label }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ══ CATEGORIES ════════════════════════════════════════════════════════ -->
    <section class="py-20 page-container">
      <div class="flex items-end justify-between mb-10">
        <div>
          <div class="flex items-center gap-2 mb-2">
            <div class="h-px w-8 bg-primary-500"></div>
            <span class="text-primary-500 font-semibold text-xs uppercase tracking-[0.18em]">Our Menu</span>
          </div>
          <h2 class="font-heading text-3xl md:text-4xl font-bold text-warm-900 dark:text-white">Explore by Category</h2>
        </div>
        <RouterLink to="/menu" class="hidden sm:flex items-center gap-1.5 text-sm text-primary-600 dark:text-primary-400 font-semibold hover:gap-3 transition-all duration-200">
          View full menu <ArrowRight :size="16" />
        </RouterLink>
      </div>

      <div class="flex gap-5 overflow-x-auto pb-4 -mx-4 px-4 snap-x snap-mandatory scrollbar-hide">
        <RouterLink to="/menu" class="flex flex-col items-center gap-3 shrink-0 snap-start group">
          <div class="w-[4.5rem] h-[4.5rem] rounded-2xl bg-primary-500 flex items-center justify-center group-hover:scale-110 group-hover:shadow-lg group-hover:shadow-primary-500/40 transition-all duration-300">
            <UtensilsCrossed :size="26" class="text-white" />
          </div>
          <span class="text-xs font-semibold text-warm-700 dark:text-warm-300">All Items</span>
        </RouterLink>

        <RouterLink
          v-for="cat in (categoriesData ?? [])"
          :key="cat.id"
          :to="`/menu?category=${cat.slug}`"
          class="flex flex-col items-center gap-3 shrink-0 snap-start group"
        >
          <div class="w-[4.5rem] h-[4.5rem] rounded-2xl overflow-hidden bg-warm-100 dark:bg-warm-800 ring-2 ring-transparent group-hover:ring-primary-500 group-hover:scale-110 transition-all duration-300 shadow-sm">
            <img v-if="cat.image" :src="cat.image" :alt="cat.name" class="w-full h-full object-cover" />
            <div v-else class="w-full h-full flex items-center justify-center">
              <ChefHat :size="24" class="text-warm-400" />
            </div>
          </div>
          <span class="text-xs font-semibold text-warm-700 dark:text-warm-300 text-center max-w-[4.5rem] leading-tight">{{ cat.name }}</span>
        </RouterLink>
      </div>
    </section>

    <!-- ══ FEATURED DISHES ═══════════════════════════════════════════════════ -->
    <section class="py-20 bg-warm-50 dark:bg-gray-900/60">
      <div class="page-container">
        <div class="flex items-end justify-between mb-10">
          <div>
            <div class="flex items-center gap-2 mb-2">
              <div class="h-px w-8 bg-primary-500"></div>
              <span class="text-primary-500 font-semibold text-xs uppercase tracking-[0.18em]">Chef's Picks</span>
            </div>
            <h2 class="font-heading text-3xl md:text-4xl font-bold text-warm-900 dark:text-white">Featured Dishes</h2>
          </div>
          <RouterLink to="/menu" class="hidden sm:flex items-center gap-1.5 text-sm text-primary-600 dark:text-primary-400 font-semibold hover:gap-3 transition-all duration-200">
            See all dishes <ArrowRight :size="16" />
          </RouterLink>
        </div>

        <div v-if="featuredLoading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
          <div v-for="i in 4" :key="i" class="card overflow-hidden">
            <div class="skeleton aspect-[4/3]"></div>
            <div class="p-4 space-y-2">
              <div class="skeleton h-5 w-3/4 rounded-lg"></div>
              <div class="skeleton h-4 w-full rounded-lg"></div>
              <div class="skeleton h-4 w-1/2 rounded-lg"></div>
            </div>
          </div>
        </div>

        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
          <DishCard v-for="dish in (featuredData ?? [])" :key="dish.id" :dish="dish" />
        </div>

        <div class="mt-10 text-center sm:hidden">
          <RouterLink to="/menu" class="btn-outline">Browse Full Menu <ArrowRight :size="16" /></RouterLink>
        </div>
      </div>
    </section>

    <!-- ══ HOW IT WORKS ══════════════════════════════════════════════════════ -->
    <section class="py-24 page-container">
      <div class="text-center mb-16">
        <div class="flex items-center justify-center gap-2 mb-3">
          <div class="h-px w-8 bg-primary-500"></div>
          <span class="text-primary-500 font-semibold text-xs uppercase tracking-[0.18em]">Simple Process</span>
          <div class="h-px w-8 bg-primary-500"></div>
        </div>
        <h2 class="font-heading text-3xl md:text-4xl font-bold text-warm-900 dark:text-white mb-3">How It Works</h2>
        <p class="text-warm-500 dark:text-warm-400 max-w-md mx-auto">Order in four easy steps — no friction, no waiting, just great food.</p>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 relative">
        <!-- Connector line desktop -->
        <div class="hidden lg:block absolute top-10 left-[12.5%] right-[12.5%] h-px bg-gradient-to-r from-transparent via-warm-200 dark:via-warm-700 to-transparent z-0 pointer-events-none"></div>

        <div
          v-for="item in howItWorks"
          :key="item.step"
          class="relative z-10 flex flex-col items-center text-center group"
        >
          <div class="relative w-20 h-20 rounded-2xl bg-white dark:bg-gray-900 border-2 border-warm-200 dark:border-warm-700 flex items-center justify-center mb-6 shadow-md group-hover:border-primary-500 group-hover:shadow-primary-500/20 group-hover:shadow-lg transition-all duration-300">
            <component :is="item.icon" :size="28" class="text-warm-400 group-hover:text-primary-500 transition-colors duration-300" />
            <span class="absolute -top-3 -right-3 w-7 h-7 rounded-full bg-primary-500 text-white text-[11px] font-bold flex items-center justify-center shadow-md">{{ item.step }}</span>
          </div>
          <h3 class="font-heading font-bold text-warm-900 dark:text-white mb-2 text-base">{{ item.title }}</h3>
          <p class="text-sm text-warm-500 dark:text-warm-400 leading-relaxed max-w-[180px]">{{ item.desc }}</p>
        </div>
      </div>
    </section>

    <!-- ══ SPLIT PROMO ═══════════════════════════════════════════════════════ -->
    <section class="grid grid-cols-1 md:grid-cols-2">
      <!-- Dine-in -->
      <div class="relative overflow-hidden flex items-end p-10 md:p-14 min-h-80 group">
        <img
          src="https://images.unsplash.com/photo-1555396273-367ea4eb4db5?w=800&q=80"
          alt="Restaurant dining room"
          class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-700"
        />
        <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/40 to-transparent"></div>
        <div class="relative z-10">
          <span class="inline-block text-xs font-bold uppercase tracking-widest text-primary-300 mb-3">Dine-In Experience</span>
          <h3 class="font-heading text-2xl md:text-3xl font-bold text-white mb-3 leading-tight">Reserve Your<br>Perfect Table</h3>
          <p class="text-white/65 text-sm mb-6 max-w-xs leading-relaxed">Experience the ambiance of our dining room with attentive, personalised service.</p>
          <RouterLink to="/reservations" class="btn-primary shadow-lg">
            Book a Table <ArrowRight :size="16" />
          </RouterLink>
        </div>
      </div>

      <!-- Room service -->
      <div class="relative overflow-hidden flex items-end p-10 md:p-14 min-h-80 group">
        <img
          src="https://images.unsplash.com/photo-1631049307264-da0ec9d70304?w=800&q=80"
          alt="Luxury hotel room"
          class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-700"
        />
        <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/40 to-transparent"></div>
        <div class="relative z-10">
          <span class="inline-block text-xs font-bold uppercase tracking-widest text-blue-300 mb-3">Room Service</span>
          <h3 class="font-heading text-2xl md:text-3xl font-bold text-white mb-3 leading-tight">Stay & Order<br>Any Time</h3>
          <p class="text-white/65 text-sm mb-6 max-w-xs leading-relaxed">Hotel guests can order to their room and charge directly to their bill — 24 hours a day.</p>
          <RouterLink to="/rooms" class="inline-flex items-center gap-2 rounded-xl px-5 py-2.5 text-sm font-semibold border-2 border-white/30 text-white hover:border-white hover:bg-white/10 transition-all duration-200">
            <BedDouble :size="16" /> Book a Room <ArrowRight :size="16" />
          </RouterLink>
        </div>
      </div>
    </section>

    <!-- ══ FEATURES GRID ═════════════════════════════════════════════════════ -->
    <section class="py-24 bg-warm-50 dark:bg-gray-900/60">
      <div class="page-container">
        <div class="text-center mb-14">
          <div class="flex items-center justify-center gap-2 mb-3">
            <div class="h-px w-8 bg-primary-500"></div>
            <span class="text-primary-500 font-semibold text-xs uppercase tracking-[0.18em]">Why Choose Us</span>
            <div class="h-px w-8 bg-primary-500"></div>
          </div>
          <h2 class="font-heading text-3xl md:text-4xl font-bold text-warm-900 dark:text-white mb-3">Everything in One Place</h2>
          <p class="text-warm-500 dark:text-warm-400 max-w-lg mx-auto">A complete hospitality experience — from ordering to payment to room booking.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
          <div
            v-for="feature in features"
            :key="feature.title"
            class="group p-6 rounded-2xl bg-white dark:bg-gray-900 border border-warm-100 dark:border-warm-800 hover:border-primary-200 dark:hover:border-primary-800 hover:shadow-xl hover:shadow-primary-500/5 hover:-translate-y-1 transition-all duration-300 cursor-default"
          >
            <div class="w-11 h-11 rounded-xl bg-primary-50 dark:bg-primary-900/20 flex items-center justify-center mb-4 group-hover:bg-primary-500 transition-colors duration-300">
              <component :is="feature.icon" :size="20" class="text-primary-500 group-hover:text-white transition-colors duration-300" />
            </div>
            <h3 class="font-heading font-bold text-warm-900 dark:text-white mb-1.5 text-base">{{ feature.title }}</h3>
            <p class="text-sm text-warm-500 dark:text-warm-400 leading-relaxed">{{ feature.desc }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ══ TESTIMONIALS CAROUSEL ═════════════════════════════════════════════ -->
    <section class="py-24 bg-warm-950 dark:bg-gray-950 overflow-hidden">
      <div class="page-container">
        <div class="text-center mb-14">
          <div class="flex items-center justify-center gap-2 mb-3">
            <div class="h-px w-8 bg-primary-500"></div>
            <span class="text-primary-500 font-semibold text-xs uppercase tracking-[0.18em]">Guest Reviews</span>
            <div class="h-px w-8 bg-primary-500"></div>
          </div>
          <h2 class="font-heading text-3xl md:text-4xl font-bold text-white mb-3">What Our Guests Say</h2>
          <p class="text-warm-400 max-w-md mx-auto">Trusted by thousands of diners and hotel guests across Addis Ababa.</p>
        </div>

        <!-- Carousel -->
        <div class="relative max-w-3xl mx-auto">
          <Transition name="fade-slide" mode="out-in">
            <div :key="activeTestimonial" class="card bg-warm-800 dark:bg-warm-800 border-warm-700 p-8 md:p-10 text-center">
              <!-- Stars -->
              <div class="flex justify-center gap-1 mb-6">
                <Star v-for="i in 5" :key="i" :size="18" class="text-yellow-400 fill-yellow-400" />
              </div>
              <p class="text-white/85 text-base md:text-lg leading-relaxed mb-8 italic">
                "{{ testimonials[activeTestimonial].text }}"
              </p>
              <div class="flex items-center justify-center gap-3">
                <div class="w-10 h-10 rounded-full bg-primary-500 flex items-center justify-center font-bold text-white text-sm">
                  {{ testimonials[activeTestimonial].name[0] }}
                </div>
                <div class="text-left">
                  <p class="font-semibold text-white text-sm">{{ testimonials[activeTestimonial].name }}</p>
                  <p class="text-xs text-warm-400">{{ testimonials[activeTestimonial].role }}</p>
                </div>
              </div>
            </div>
          </Transition>

          <!-- Nav buttons -->
          <button @click="prevTestimonial" class="absolute -left-5 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-warm-700 hover:bg-primary-500 flex items-center justify-center text-white transition-colors duration-200 shadow-lg" aria-label="Previous review">
            <ChevronLeft :size="18" />
          </button>
          <button @click="nextTestimonial" class="absolute -right-5 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-warm-700 hover:bg-primary-500 flex items-center justify-center text-white transition-colors duration-200 shadow-lg" aria-label="Next review">
            <ChevronRight :size="18" />
          </button>

          <!-- Dots -->
          <div class="flex justify-center gap-2 mt-6">
            <button
              v-for="(_, i) in testimonials"
              :key="i"
              @click="activeTestimonial = i"
              :class="['w-2 h-2 rounded-full transition-all duration-300', i === activeTestimonial ? 'bg-primary-500 w-6' : 'bg-warm-600 hover:bg-warm-500']"
              :aria-label="`Go to review ${i + 1}`"
            ></button>
          </div>
        </div>
      </div>
    </section>

    <!-- ══ CONTACT BAR ════════════════════════════════════════════════════════ -->
    <section class="py-12 bg-white dark:bg-gray-900 border-y border-warm-100 dark:border-warm-800">
      <div class="page-container">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 text-center sm:text-left">
          <div class="flex flex-col sm:flex-row items-center sm:items-start gap-4">
            <div class="w-12 h-12 rounded-2xl bg-primary-50 dark:bg-primary-900/20 border border-primary-100 dark:border-primary-800 flex items-center justify-center shrink-0">
              <Phone :size="20" class="text-primary-500" />
            </div>
            <div>
              <p class="text-xs font-semibold uppercase tracking-wider text-warm-400 mb-0.5">Call Us Anytime</p>
              <p class="font-heading font-bold text-warm-900 dark:text-white">+251 11 000 0000</p>
              <p class="text-xs text-warm-400 mt-0.5">Mon–Sun, 07:00–23:00</p>
            </div>
          </div>
          <div class="flex flex-col sm:flex-row items-center sm:items-start gap-4">
            <div class="w-12 h-12 rounded-2xl bg-primary-50 dark:bg-primary-900/20 border border-primary-100 dark:border-primary-800 flex items-center justify-center shrink-0">
              <MapPin :size="20" class="text-primary-500" />
            </div>
            <div>
              <p class="text-xs font-semibold uppercase tracking-wider text-warm-400 mb-0.5">Find Us</p>
              <p class="font-heading font-bold text-warm-900 dark:text-white">Bole Road, Addis Ababa</p>
              <p class="text-xs text-warm-400 mt-0.5">Near Bole International Airport</p>
            </div>
          </div>
          <div class="flex flex-col sm:flex-row items-center sm:items-start gap-4">
            <div class="w-12 h-12 rounded-2xl bg-primary-50 dark:bg-primary-900/20 border border-primary-100 dark:border-primary-800 flex items-center justify-center shrink-0">
              <Clock :size="20" class="text-primary-500" />
            </div>
            <div>
              <p class="text-xs font-semibold uppercase tracking-wider text-warm-400 mb-0.5">Opening Hours</p>
              <p class="font-heading font-bold text-warm-900 dark:text-white">Daily 07:00 – 23:00</p>
              <p class="text-xs text-warm-400 mt-0.5">Room service available 24/7</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ══ FINAL CTA ══════════════════════════════════════════════════════════ -->
    <section class="relative overflow-hidden py-28">
      <img
        src="https://images.unsplash.com/photo-1544025162-d76694265947?w=1600&q=80"
        alt="Restaurant interior"
        class="absolute inset-0 w-full h-full object-cover"
      />
      <div class="absolute inset-0 bg-gradient-to-br from-black/95 via-black/75 to-primary-900/60"></div>
      <!-- Decorative accent -->
      <div class="absolute top-0 left-1/2 -translate-x-1/2 w-px h-16 bg-gradient-to-b from-primary-500 to-transparent"></div>

      <div class="page-container relative z-10 text-center">
        <div class="flex items-center justify-center gap-2 mb-4">
          <div class="h-px w-8 bg-primary-400"></div>
          <span class="text-primary-400 font-semibold text-xs uppercase tracking-[0.18em]">Ready to Enjoy?</span>
          <div class="h-px w-8 bg-primary-400"></div>
        </div>
        <h2 class="font-heading text-4xl md:text-5xl lg:text-6xl font-bold text-white mb-5 leading-[1.1]">
          Your Table is<br>
          <span class="text-primary-400">Waiting for You</span>
        </h2>
        <p class="text-white/65 text-lg mb-12 max-w-xl mx-auto leading-relaxed">
          Order online, reserve a table, or scan a QR code to start your dining experience right now.
        </p>
        <div class="flex flex-wrap gap-4 justify-center">
          <RouterLink to="/menu" class="group btn-primary text-base px-9 py-4 shadow-xl shadow-primary-500/30 hover:scale-[1.03] transition-all duration-200">
            Order Now <ArrowRight :size="18" class="group-hover:translate-x-1 transition-transform" />
          </RouterLink>
          <RouterLink to="/reservations" class="group inline-flex items-center gap-2 rounded-xl px-9 py-4 text-base font-semibold border-2 border-white/25 text-white hover:border-white/60 hover:bg-white/8 transition-all duration-200">
            <CalendarCheck :size="18" /> Reserve a Table
          </RouterLink>
        </div>
      </div>
    </section>

  </DefaultLayout>
</template>

<style scoped>
@keyframes heroZoom {
  from { transform: scale(1.05); }
  to   { transform: scale(1.12); }
}

.fade-slide-enter-active,
.fade-slide-leave-active {
  transition: opacity 0.4s ease, transform 0.4s ease;
}
.fade-slide-enter-from {
  opacity: 0;
  transform: translateX(24px);
}
.fade-slide-leave-to {
  opacity: 0;
  transform: translateX(-24px);
}
</style>
