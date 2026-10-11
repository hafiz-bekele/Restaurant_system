<script setup>
import { ref } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import { RouterLink } from 'vue-router'
import {
  ArrowRight, Star, Clock, MapPin, QrCode, ChefHat, Truck,
  CalendarCheck, BedDouble, Award, ShieldCheck, Smile, Phone,
  UtensilsCrossed, Leaf, Flame, Heart,
} from '@lucide/vue'
import DefaultLayout from '../layouts/DefaultLayout.vue'
import { menuApi, guestApi } from '../services/api'
import DishCard from '../components/menu/DishCard.vue'

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

const { data: bannersData } = useQuery({
  queryKey: ['banners'],
  queryFn:  () => guestApi.banners().then(r => r.data.data),
  staleTime: 3600000,
})

const activeSlide = ref(0)

const stats = [
  { value: '4.9★', label: 'Average Rating' },
  { value: '15 min', label: 'Avg Delivery' },
  { value: '200+', label: 'Menu Items' },
  { value: '50K+', label: 'Happy Guests' },
]

const howItWorks = [
  { icon: QrCode,        step: '01', title: 'Scan or Browse',      desc: 'Scan the table QR code or browse our menu online. No login required.' },
  { icon: UtensilsCrossed, step: '02', title: 'Build Your Order',  desc: 'Choose your dishes, customize with extras, add special notes.' },
  { icon: ChefHat,       step: '03', title: 'Kitchen Prepares',    desc: 'Our chefs receive your order instantly and begin preparing it fresh.' },
  { icon: Truck,         step: '04', title: 'Enjoy Your Meal',     desc: 'Your order is delivered to your table or door in record time.' },
]

const features = [
  { icon: QrCode,      title: 'QR Ordering',       desc: 'Scan any table or room QR to order instantly — no app download, no login.' },
  { icon: BedDouble,   title: 'Room Service',       desc: 'Hotel guests can charge orders directly to their room folio.' },
  { icon: CalendarCheck, title: 'Table Reservations', desc: 'Book your table in seconds and arrive to a perfectly set experience.' },
  { icon: ShieldCheck, title: 'Secure Payments',   desc: 'Pay with Chapa, Telebirr, card, or cash — all transactions encrypted.' },
  { icon: Leaf,        title: 'Dietary Filters',   desc: 'Easily filter vegan, vegetarian, halal, and allergen-free options.' },
  { icon: Smile,       title: 'Live Order Tracking', desc: 'Watch your order move from kitchen to table in real time.' },
]

const testimonials = [
  { name: 'Abebe K.', role: 'Regular Guest', text: 'The QR ordering is incredible — I placed my order before even sitting down. Food was hot and ready in minutes.', stars: 5 },
  { name: 'Sara M.', role: 'Business Traveler', text: 'Room service charged straight to my bill. No hassle, no waiting. This is how hotels should work.', stars: 5 },
  { name: 'Dawit T.', role: 'Food Enthusiast', text: 'The menu is extensive and the dietary filters saved me so much time. Found perfect vegan options instantly.', stars: 5 },
]

const dietaryIcons = [
  { icon: Leaf,  label: 'Vegan Options' },
  { icon: Flame, label: 'Spice Levels' },
  { icon: Heart, label: 'Allergen Info' },
  { icon: Award, label: 'Chef Specials' },
]
</script>

<template>
  <DefaultLayout>

    <!-- ══ HERO ═══════════════════════════════════════════════════════════════ -->
    <section class="relative overflow-hidden min-h-[92vh] flex items-center">
      <!-- Background image with overlay -->
      <div class="absolute inset-0">
        <img
          src="https://images.unsplash.com/photo-1414235077428-338989a2e8c0?w=1920&q=80"
          alt="Fine dining"
          class="w-full h-full object-cover"
        />
        <div class="absolute inset-0 bg-gradient-to-r from-warm-950/95 via-warm-900/80 to-warm-900/40"></div>
      </div>

      <div class="page-container relative z-10 py-20">
        <div class="max-w-2xl">
          <!-- Badge -->
          <div class="flex items-center gap-2 mb-6">
            <span class="badge badge-orange text-xs">Now Open</span>
            <span class="text-warm-400 text-sm">Free delivery on orders over $30</span>
          </div>

          <h1 class="font-heading text-5xl md:text-6xl lg:text-7xl font-bold leading-[1.1] text-white mb-6">
            Taste the<br>
            <span class="text-primary-400 relative">
              Difference
              <svg class="absolute -bottom-1 left-0 w-full" viewBox="0 0 200 8" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M1 5.5 C40 1.5 80 7.5 120 3.5 C160 -0.5 190 5 199 4" stroke="#f97316" stroke-width="2.5" stroke-linecap="round"/>
              </svg>
            </span>
          </h1>

          <p class="text-lg md:text-xl text-warm-300 mb-10 max-w-lg leading-relaxed">
            From our kitchen to your table — crafted with the finest ingredients, served with love. Dine in, order online, or book a room.
          </p>

          <!-- CTAs -->
          <div class="flex flex-wrap gap-4 mb-12">
            <RouterLink to="/menu" class="btn-primary text-base px-8 py-3.5 shadow-lg shadow-primary-500/30">
              Order Now <ArrowRight :size="18" />
            </RouterLink>
            <RouterLink to="/reservations" class="inline-flex items-center gap-2 rounded-xl px-8 py-3.5 text-base font-semibold border-2 border-white/30 text-white hover:border-white/60 hover:bg-white/10 transition-all duration-200">
              Reserve a Table
            </RouterLink>
          </div>

          <!-- Trust signals -->
          <div class="flex flex-wrap gap-6">
            <div v-for="stat in stats" :key="stat.label" class="flex flex-col">
              <span class="font-heading font-bold text-xl text-white">{{ stat.value }}</span>
              <span class="text-xs text-warm-400">{{ stat.label }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Floating card -->
      <div class="absolute bottom-8 right-8 hidden lg:flex glass rounded-2xl p-5 gap-4 items-center shadow-float max-w-xs">
        <div class="w-14 h-14 rounded-xl bg-primary-500/20 flex items-center justify-center shrink-0">
          <QrCode :size="28" class="text-primary-400" />
        </div>
        <div>
          <p class="font-semibold text-warm-900 dark:text-white text-sm">Instant QR Ordering</p>
          <p class="text-xs text-warm-500 dark:text-warm-400 mt-0.5">Scan any table to order in seconds. No app needed.</p>
        </div>
      </div>
    </section>

    <!-- ══ DIETARY BADGES ════════════════════════════════════════════════════ -->
    <section class="bg-primary-600 dark:bg-primary-700 py-4">
      <div class="page-container">
        <div class="flex flex-wrap items-center justify-center gap-8">
          <div v-for="item in dietaryIcons" :key="item.label" class="flex items-center gap-2 text-white/90">
            <component :is="item.icon" :size="16" class="text-white" />
            <span class="text-sm font-medium">{{ item.label }}</span>
          </div>
        </div>
      </div>
    </section>

    <!-- ══ CATEGORIES ════════════════════════════════════════════════════════ -->
    <section class="py-16 page-container">
      <div class="flex items-end justify-between mb-8">
        <div>
          <p class="text-primary-500 font-semibold text-sm uppercase tracking-wider mb-1">Our Menu</p>
          <h2 class="font-heading text-3xl md:text-4xl font-bold text-warm-900 dark:text-white">Explore Categories</h2>
        </div>
        <RouterLink to="/menu" class="hidden sm:flex items-center gap-1 text-sm text-primary-600 dark:text-primary-400 font-semibold hover:gap-2 transition-all">
          View all <ArrowRight :size="16" />
        </RouterLink>
      </div>

      <div class="flex gap-4 overflow-x-auto pb-4 -mx-4 px-4 snap-x snap-mandatory scrollbar-hide">
        <!-- All items button -->
        <RouterLink
          to="/menu"
          class="flex flex-col items-center gap-3 shrink-0 snap-start group"
        >
          <div class="w-20 h-20 rounded-2xl bg-primary-500 flex items-center justify-center group-hover:scale-105 transition-transform duration-300 shadow-md shadow-primary-500/30">
            <UtensilsCrossed :size="28" class="text-white" />
          </div>
          <span class="text-xs font-semibold text-warm-700 dark:text-warm-300">All Items</span>
        </RouterLink>

        <RouterLink
          v-for="cat in (categoriesData ?? [])"
          :key="cat.id"
          :to="`/menu?category=${cat.slug}`"
          class="flex flex-col items-center gap-3 shrink-0 snap-start group"
        >
          <div class="w-20 h-20 rounded-2xl overflow-hidden bg-warm-100 dark:bg-warm-800 group-hover:ring-2 ring-primary-500 group-hover:scale-105 transition-all duration-300">
            <img
              v-if="cat.image"
              :src="cat.image"
              :alt="cat.name"
              class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
            />
            <div v-else class="w-full h-full flex items-center justify-center">
              <ChefHat :size="28" class="text-warm-400" />
            </div>
          </div>
          <span class="text-xs font-semibold text-warm-700 dark:text-warm-300 text-center max-w-20 leading-tight">{{ cat.name }}</span>
        </RouterLink>
      </div>
    </section>

    <!-- ══ FEATURED DISHES ───────────────────────────────────────────────── -->
    <section class="py-16 bg-warm-50 dark:bg-gray-900/50">
      <div class="page-container">
        <div class="flex items-end justify-between mb-8">
          <div>
            <p class="text-primary-500 font-semibold text-sm uppercase tracking-wider mb-1">Chef's Picks</p>
            <h2 class="font-heading text-3xl md:text-4xl font-bold text-warm-900 dark:text-white">Featured Dishes</h2>
          </div>
          <RouterLink to="/menu" class="hidden sm:flex items-center gap-1 text-sm text-primary-600 dark:text-primary-400 font-semibold hover:gap-2 transition-all">
            View full menu <ArrowRight :size="16" />
          </RouterLink>
        </div>

        <!-- Skeleton -->
        <div v-if="featuredLoading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
          <div v-for="i in 4" :key="i" class="card overflow-hidden">
            <div class="skeleton aspect-[4/3]"></div>
            <div class="p-4 space-y-2">
              <div class="skeleton h-5 w-3/4"></div>
              <div class="skeleton h-4 w-full"></div>
              <div class="skeleton h-4 w-1/2"></div>
            </div>
          </div>
        </div>

        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
          <DishCard v-for="dish in (featuredData ?? [])" :key="dish.id" :dish="dish" />
        </div>

        <!-- Mobile view-all -->
        <div class="mt-8 text-center sm:hidden">
          <RouterLink to="/menu" class="btn-outline">Browse Full Menu <ArrowRight :size="16" /></RouterLink>
        </div>
      </div>
    </section>

    <!-- ══ HOW IT WORKS ──────────────────────────────────────────────────── -->
    <section class="py-20 page-container">
      <div class="text-center mb-14">
        <p class="text-primary-500 font-semibold text-sm uppercase tracking-wider mb-2">Simple Process</p>
        <h2 class="font-heading text-3xl md:text-4xl font-bold text-warm-900 dark:text-white">How It Works</h2>
        <p class="text-warm-500 dark:text-warm-400 mt-3 max-w-lg mx-auto">Order in four easy steps — no friction, no waiting, just great food.</p>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
        <div
          v-for="(item, idx) in howItWorks"
          :key="item.step"
          class="relative flex flex-col items-center text-center group"
        >
          <!-- Connector line (desktop) -->
          <div v-if="idx < howItWorks.length - 1" class="hidden lg:block absolute top-10 left-[calc(50%+2.5rem)] w-[calc(100%-5rem)] h-px bg-warm-200 dark:bg-warm-700 z-0"></div>

          <!-- Icon -->
          <div class="relative z-10 w-20 h-20 rounded-2xl bg-primary-50 dark:bg-primary-900/20 border-2 border-primary-100 dark:border-primary-800 flex items-center justify-center mb-5 group-hover:bg-primary-500 group-hover:border-primary-500 transition-all duration-300">
            <component :is="item.icon" :size="28" class="text-primary-500 group-hover:text-white transition-colors duration-300" />
            <span class="absolute -top-2 -right-2 w-6 h-6 rounded-full bg-primary-500 text-white text-[10px] font-bold flex items-center justify-center">{{ item.step }}</span>
          </div>

          <h3 class="font-heading font-bold text-warm-900 dark:text-white mb-2">{{ item.title }}</h3>
          <p class="text-sm text-warm-500 dark:text-warm-400 leading-relaxed">{{ item.desc }}</p>
        </div>
      </div>
    </section>

    <!-- ══ SPLIT PROMO — DINE IN + HOTEL ────────────────────────────────── -->
    <section class="py-0 grid grid-cols-1 md:grid-cols-2 min-h-80">
      <!-- Dine-in -->
      <div class="relative overflow-hidden flex items-end p-8 md:p-12 min-h-72">
        <img
          src="https://images.unsplash.com/photo-1555396273-367ea4eb4db5?w=800&q=80"
          alt="Restaurant dining"
          class="absolute inset-0 w-full h-full object-cover"
        />
        <div class="absolute inset-0 bg-gradient-to-t from-warm-950/90 via-warm-900/40 to-transparent"></div>
        <div class="relative z-10">
          <span class="badge badge-orange mb-3">Dine-In</span>
          <h3 class="font-heading text-2xl md:text-3xl font-bold text-white mb-2">Reserve Your Table</h3>
          <p class="text-warm-300 text-sm mb-5 max-w-xs">Experience the ambiance of our dining room. Book your table now.</p>
          <RouterLink to="/reservations" class="btn-primary shadow-lg">
            Make a Reservation <ArrowRight :size="16" />
          </RouterLink>
        </div>
      </div>

      <!-- Room service -->
      <div class="relative overflow-hidden flex items-end p-8 md:p-12 min-h-72">
        <img
          src="https://images.unsplash.com/photo-1631049307264-da0ec9d70304?w=800&q=80"
          alt="Hotel room"
          class="absolute inset-0 w-full h-full object-cover"
        />
        <div class="absolute inset-0 bg-gradient-to-t from-warm-950/90 via-warm-900/40 to-transparent"></div>
        <div class="relative z-10">
          <span class="badge badge-blue mb-3">Room Service</span>
          <h3 class="font-heading text-2xl md:text-3xl font-bold text-white mb-2">Stay & Order Anytime</h3>
          <p class="text-warm-300 text-sm mb-5 max-w-xs">Hotel guests can order to their room and charge to their bill.</p>
          <RouterLink to="/rooms" class="inline-flex items-center gap-2 rounded-xl px-5 py-2.5 text-sm font-semibold border-2 border-white/30 text-white hover:bg-white/10 transition-all duration-200 gap-2">
            <BedDouble :size="16" /> Book a Room <ArrowRight :size="16" />
          </RouterLink>
        </div>
      </div>
    </section>

    <!-- ══ FEATURES GRID ─────────────────────────────────────────────────── -->
    <section class="py-20 bg-warm-50 dark:bg-gray-900/50">
      <div class="page-container">
        <div class="text-center mb-14">
          <p class="text-primary-500 font-semibold text-sm uppercase tracking-wider mb-2">Why Choose Us</p>
          <h2 class="font-heading text-3xl md:text-4xl font-bold text-warm-900 dark:text-white">Everything You Need</h2>
          <p class="text-warm-500 dark:text-warm-400 mt-3 max-w-lg mx-auto">A complete hospitality experience — from ordering to payment to room booking, all in one place.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
          <div
            v-for="feature in features"
            :key="feature.title"
            class="card p-6 group hover:shadow-float hover:-translate-y-1 transition-all duration-300"
          >
            <div class="w-12 h-12 rounded-2xl bg-primary-50 dark:bg-primary-900/20 flex items-center justify-center mb-4 group-hover:bg-primary-500 transition-all duration-300">
              <component :is="feature.icon" :size="22" class="text-primary-500 group-hover:text-white transition-colors duration-300" />
            </div>
            <h3 class="font-heading font-bold text-warm-900 dark:text-white mb-2">{{ feature.title }}</h3>
            <p class="text-sm text-warm-500 dark:text-warm-400 leading-relaxed">{{ feature.desc }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ══ TESTIMONIALS ──────────────────────────────────────────────────── -->
    <section class="py-20 page-container">
      <div class="text-center mb-14">
        <p class="text-primary-500 font-semibold text-sm uppercase tracking-wider mb-2">Guest Reviews</p>
        <h2 class="font-heading text-3xl md:text-4xl font-bold text-warm-900 dark:text-white">What Our Guests Say</h2>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div
          v-for="review in testimonials"
          :key="review.name"
          class="card p-6 flex flex-col gap-4"
        >
          <!-- Stars -->
          <div class="flex gap-1">
            <Star
              v-for="i in 5"
              :key="i"
              :size="16"
              :class="i <= review.stars ? 'text-yellow-400 fill-yellow-400' : 'text-warm-200 dark:text-warm-700'"
            />
          </div>
          <p class="text-warm-600 dark:text-warm-300 text-sm leading-relaxed flex-1">"{{ review.text }}"</p>
          <div class="flex items-center gap-3 pt-2 border-t border-warm-100 dark:border-warm-800">
            <div class="w-9 h-9 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center font-bold text-primary-600 dark:text-primary-400 text-sm">
              {{ review.name[0] }}
            </div>
            <div>
              <p class="font-semibold text-warm-900 dark:text-white text-sm">{{ review.name }}</p>
              <p class="text-xs text-warm-400">{{ review.role }}</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ══ CONTACT BAR ───────────────────────────────────────────────────── -->
    <section class="py-10 bg-warm-100 dark:bg-warm-800/50 border-y border-warm-200 dark:border-warm-700">
      <div class="page-container flex flex-col sm:flex-row items-center justify-between gap-4 text-center sm:text-left">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-primary-500 flex items-center justify-center shrink-0">
            <Phone :size="18" class="text-white" />
          </div>
          <div>
            <p class="text-xs text-warm-500 dark:text-warm-400">Call us anytime</p>
            <p class="font-heading font-bold text-warm-900 dark:text-white">+1 (555) 000-0000</p>
          </div>
        </div>
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-primary-500 flex items-center justify-center shrink-0">
            <MapPin :size="18" class="text-white" />
          </div>
          <div>
            <p class="text-xs text-warm-500 dark:text-warm-400">Find us at</p>
            <p class="font-heading font-bold text-warm-900 dark:text-white">123 Culinary Street, Addis Ababa</p>
          </div>
        </div>
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-primary-500 flex items-center justify-center shrink-0">
            <Clock :size="18" class="text-white" />
          </div>
          <div>
            <p class="text-xs text-warm-500 dark:text-warm-400">Opening hours</p>
            <p class="font-heading font-bold text-warm-900 dark:text-white">Mon–Sun, 07:00–23:00</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ══ FINAL CTA ─────────────────────────────────────────────────────── -->
    <section class="relative overflow-hidden py-24">
      <img
        src="https://images.unsplash.com/photo-1544025162-d76694265947?w=1600&q=80"
        alt="CTA background"
        class="absolute inset-0 w-full h-full object-cover"
      />
      <div class="absolute inset-0 bg-gradient-to-r from-warm-950/95 to-primary-900/80"></div>
      <div class="page-container relative z-10 text-center">
        <p class="text-primary-300 font-semibold text-sm uppercase tracking-wider mb-3">Ready to Enjoy?</p>
        <h2 class="font-heading text-4xl md:text-5xl font-bold text-white mb-5">
          Your Table is<br>
          <span class="text-primary-400">Waiting for You</span>
        </h2>
        <p class="text-warm-300 text-lg mb-10 max-w-xl mx-auto">
          Order online, reserve a table, or scan a QR code to start your dining experience right now.
        </p>
        <div class="flex flex-wrap gap-4 justify-center">
          <RouterLink to="/menu" class="btn-primary text-base px-8 py-3.5 shadow-lg shadow-primary-500/30">
            Order Now <ArrowRight :size="18" />
          </RouterLink>
          <RouterLink to="/reservations" class="inline-flex items-center gap-2 rounded-xl px-8 py-3.5 text-base font-semibold border-2 border-white/30 text-white hover:border-white/60 hover:bg-white/10 transition-all duration-200">
            <CalendarCheck :size="18" /> Reserve a Table
          </RouterLink>
        </div>
      </div>
    </section>

  </DefaultLayout>
</template>
