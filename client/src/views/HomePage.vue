<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import { RouterLink, useRouter } from 'vue-router'
import {
  ArrowRight, Star, Clock, MapPin, QrCode, ChefHat, Truck,
  CalendarCheck, BedDouble, Award, ShieldCheck, Smile, Phone,
  UtensilsCrossed, Leaf, Flame, Heart, ChevronLeft, ChevronRight,
  CheckCircle, Sparkles, Copy, Check, Wine, Users, Percent,
  Coffee, HelpCircle, Send, ExternalLink, Calendar, Info, X
} from '@lucide/vue'
import DefaultLayout from '../layouts/DefaultLayout.vue'
import { useI18n } from 'vue-i18n'
import { menuApi } from '../services/api'
import { useCartStore } from '../stores/cart'
import { useUiStore } from '../stores/ui'
import { APP_NAME } from '../config'
import DishCard from '../components/menu/DishCard.vue'
import DishCardSkeleton from '../components/ui/DishCardSkeleton.vue'

const { t }   = useI18n()
const router = useRouter()
const cart   = useCartStore()
const ui     = useUiStore()

// ── Real Restaurant & Hotel Identity ─────────────────────────────────────────
const brandName = computed(() => t('brandName'))
const phoneReservations = '+251 91 123 4567'
const phoneFrontDesk    = '+251 11 662 8900'
const locationAddress   = 'Bole Medhanialem, Namibia St., Addis Ababa, Ethiopia'
const locationLandmark  = 'Adjacent to Edna Mall & Bole Diplomatic Corridor'
const promoCode         = 'WELCOME10'
const promoCopied       = ref(false)

function copyPromoCode() {
  navigator.clipboard.writeText(promoCode).then(() => {
    promoCopied.value = true
    ui.toast(`Promo code ${promoCode} copied! Use at checkout for 10% off.`, 'success')
    setTimeout(() => { promoCopied.value = false }, 2500)
  }).catch(() => {
    ui.toast(`Use promo code: ${promoCode}`, 'info')
  })
}

// ── Data queries with realistic fallback ─────────────────────────────────────
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

// Curated realistic dishes fallback if API is warming up or empty
const fallbackDishes = [
  {
    id: 1,
    name: 'Prime Angus Ribeye Steak',
    slug: 'prime-angus-ribeye-steak',
    description: 'Char-grilled 300g Prime Angus ribeye with rosemary-garlic butter, truffle mashed potatoes, and roasted asparagus.',
    price: 36.50,
    image: 'https://images.unsplash.com/photo-1558030006-450675393462?w=700&auto=format&fit=crop&q=80',
    calories: 780,
    prep_time: 20,
    is_vegetarian: false,
    is_vegan: false,
    is_featured: true,
    spice_level: 0,
    avg_rating: 4.95,
    orders_count: 142,
    category: 'Steaks & Grills'
  },
  {
    id: 3,
    name: 'Doro Wat Deluxe Feast',
    slug: 'doro-wat-deluxe-feast',
    description: 'Slow-simmered tender chicken in rich 12-hour berbere spice sauce with hard-boiled eggs, served with freshly baked teff injera.',
    price: 24.00,
    image: 'https://images.unsplash.com/photo-1541518763669-27fef04b14ea?w=700&auto=format&fit=crop&q=80',
    calories: 620,
    prep_time: 18,
    is_vegetarian: false,
    is_vegan: false,
    is_featured: true,
    spice_level: 2,
    avg_rating: 4.98,
    orders_count: 185,
    category: 'Ethiopian Heritage'
  },
  {
    id: 2,
    name: 'Artisanal Margherita Pizza',
    slug: 'artisanal-margherita-pizza',
    description: 'San Marzano tomato sauce, fresh buffalo mozzarella, fragrant basil leaves, and cold-pressed extra virgin olive oil.',
    price: 16.00,
    image: 'https://images.unsplash.com/photo-1604382355076-af4b0eb60143?w=700&auto=format&fit=crop&q=80',
    calories: 650,
    prep_time: 15,
    is_vegetarian: true,
    is_vegan: false,
    is_featured: true,
    spice_level: 0,
    avg_rating: 4.88,
    orders_count: 210,
    category: 'Woodfired Pizza & Pasta'
  },
  {
    id: 4,
    name: 'Wild Truffle Fettuccine',
    slug: 'wild-truffle-fettuccine',
    description: 'Handmade fettuccine ribbons tossed in black truffle cream sauce, shaved Parmigiano-Reggiano, and wild forest mushrooms.',
    price: 22.50,
    image: 'https://images.unsplash.com/photo-1621996346565-e3d5d6281136?w=700&auto=format&fit=crop&q=80',
    calories: 580,
    prep_time: 16,
    is_vegetarian: true,
    is_vegan: false,
    is_featured: true,
    spice_level: 0,
    avg_rating: 4.92,
    orders_count: 98,
    category: 'Woodfired Pizza & Pasta'
  },
  {
    id: 5,
    name: 'Shiro Tegabino Hot Pot',
    slug: 'shiro-tegabino-hot-pot',
    description: 'Aromatic powdered chickpea and split-pea clay pot stew cooked with garlic, ginger, and spiced butter. Served sizzling with injera.',
    price: 15.00,
    image: 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=700&auto=format&fit=crop&q=80',
    calories: 420,
    prep_time: 12,
    is_vegetarian: true,
    is_vegan: true,
    is_featured: true,
    spice_level: 1,
    avg_rating: 4.85,
    orders_count: 130,
    category: 'Ethiopian Heritage'
  },
  {
    id: 6,
    name: 'Pan-Seared Atlantic Salmon',
    slug: 'pan-seared-salmon-fillet',
    description: 'Crispy skin Atlantic salmon over saffron risotto, baby spinach, and citrus dill beurre blanc.',
    price: 28.00,
    image: 'https://images.unsplash.com/photo-1467003909585-2f8a72700288?w=700&auto=format&fit=crop&q=80',
    calories: 540,
    prep_time: 18,
    is_vegetarian: false,
    is_vegan: false,
    is_featured: true,
    spice_level: 0,
    avg_rating: 4.87,
    orders_count: 84,
    category: 'Steaks & Grills'
  },
  {
    id: 8,
    name: 'Classic Italian Tiramisu',
    slug: 'classic-italian-tiramisu',
    description: 'Espresso-soaked ladyfingers layered with velvety mascarpone cream and dusted with Valrhona cocoa.',
    price: 9.50,
    image: 'https://images.unsplash.com/photo-1571877227200-a0d98ea607e9?w=700&auto=format&fit=crop&q=80',
    calories: 410,
    prep_time: 5,
    is_vegetarian: true,
    is_vegan: false,
    is_featured: true,
    spice_level: 0,
    avg_rating: 4.94,
    orders_count: 160,
    category: 'Desserts & Mixology'
  },
  {
    id: 9,
    name: 'Passionfruit Mint Spritzer',
    slug: 'passionfruit-mint-spritzer',
    description: 'Sparkling fresh passionfruit pulp, fresh garden mint, lime juice, and organic agave nectar over crushed crystal ice.',
    price: 6.50,
    image: 'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?w=700&auto=format&fit=crop&q=80',
    calories: 120,
    prep_time: 5,
    is_vegetarian: true,
    is_vegan: true,
    is_featured: true,
    spice_level: 0,
    avg_rating: 4.82,
    orders_count: 195,
    category: 'Desserts & Mixology'
  }
]

const activeDishes = computed(() => {
  if (featuredData.value && featuredData.value.length > 0) {
    return featuredData.value
  }
  return fallbackDishes
})

// ── Interactive Menu Filter Tabs ─────────────────────────────────────────────
const menuTabs = computed(() => [
  { id: 'All Highlights', label: t('menu.allHighlights') },
  { id: 'Chef Specials', label: t('menu.chefSpecials') },
  { id: 'Ethiopian Heritage', label: t('menu.ethiopianHeritage') },
  { id: 'Steaks & Grills', label: t('menu.steaksGrills') },
  { id: 'Woodfired Pizza & Pasta', label: t('menu.woodfiredPizzaPasta') },
  { id: 'Desserts & Mixology', label: t('menu.dessertsMixology') }
])
const activeMenuTab = ref('All Highlights')

const filteredDishes = computed(() => {
  const dishes = activeDishes.value
  if (activeMenuTab.value === 'All Highlights') return dishes.slice(0, 8)
  if (activeMenuTab.value === 'Chef Specials') return dishes.filter(d => d.avg_rating >= 4.9 || d.is_featured)
  if (activeMenuTab.value === 'Ethiopian Heritage') return dishes.filter(d => (d.category === 'Ethiopian Heritage') || d.slug?.includes('doro') || d.slug?.includes('shiro'))
  if (activeMenuTab.value === 'Steaks & Grills') return dishes.filter(d => (d.category === 'Steaks & Grills') || d.slug?.includes('steak') || d.slug?.includes('salmon'))
  if (activeMenuTab.value === 'Woodfired Pizza & Pasta') return dishes.filter(d => (d.category === 'Woodfired Pizza & Pasta') || d.slug?.includes('pizza') || d.slug?.includes('fettuccine'))
  if (activeMenuTab.value === 'Desserts & Mixology') return dishes.filter(d => (d.category === 'Desserts & Mixology') || d.slug?.includes('tiramisu') || d.slug?.includes('spritzer'))
  return dishes
})

// ── Interactive Quick Reservation Widget ─────────────────────────────────────
const quickBooking = ref({
  partySize: 2,
  dateOption: 'tonight',
  timeSlot: '19:30',
  seatingZone: 'Main Dining Hall',
})

function submitQuickBooking() {
  const now = new Date()
  let targetDate = new Date()

  if (quickBooking.value.dateOption === 'tomorrow') {
    targetDate.setDate(now.getDate() + 1)
  } else if (quickBooking.value.dateOption === 'weekend') {
    const daysUntilFriday = (5 - now.getDay() + 7) % 7 || 7
    targetDate.setDate(now.getDate() + daysUntilFriday)
  }

  const [hours, minutes] = quickBooking.value.timeSlot.split(':')
  targetDate.setHours(Number(hours), Number(minutes), 0, 0)

  // Format to YYYY-MM-DDTHH:mm for datetime-local
  const year = targetDate.getFullYear()
  const month = String(targetDate.getMonth() + 1).padStart(2, '0')
  const day = String(targetDate.getDate()).padStart(2, '0')
  const isoDateTime = `${year}-${month}-${day}T${quickBooking.value.timeSlot}`

  router.push({
    path: '/reservations',
    query: {
      party_size: quickBooking.value.partySize,
      reserved_at: isoDateTime,
      notes: `Seating preference: ${quickBooking.value.seatingZone}`
    }
  })
}

// ── Interactive 4-Way Experience Showcase ────────────────────────────────────
const experiences = computed(() => [
  {
    id: 'dining',
    tabName: t('experiences.diningTab'),
    title: t('experiences.diningTitle'),
    subtitle: t('experiences.diningSub'),
    image: 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?w=1200&auto=format&fit=crop&q=80',
    badge: t('experiences.diningBadge'),
    perks: [
      t('experiences.diningP1'),
      t('experiences.diningP2'),
      t('experiences.diningP3'),
      t('experiences.diningP4')
    ],
    ctaText: t('experiences.diningCta'),
    ctaLink: '/reservations'
  },
  {
    id: 'suites',
    tabName: t('experiences.suitesTab'),
    title: t('experiences.suitesTitle'),
    subtitle: t('experiences.suitesSub'),
    image: 'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?w=1200&auto=format&fit=crop&q=80',
    badge: t('experiences.suitesBadge'),
    perks: [
      t('experiences.suitesP1'),
      t('experiences.suitesP2'),
      t('experiences.suitesP3'),
      t('experiences.suitesP4')
    ],
    ctaText: t('experiences.suitesCta'),
    ctaLink: '/rooms'
  },
  {
    id: 'terrace',
    tabName: t('experiences.terraceTab'),
    title: t('experiences.terraceTitle'),
    subtitle: t('experiences.terraceSub'),
    image: 'https://images.unsplash.com/photo-1555396273-367ea4eb4db5?w=1200&auto=format&fit=crop&q=80',
    badge: t('experiences.terraceBadge'),
    perks: [
      t('experiences.terraceP1'),
      t('experiences.terraceP2'),
      t('experiences.terraceP3'),
      t('experiences.terraceP4')
    ],
    ctaText: t('experiences.terraceCta'),
    ctaLink: '/menu'
  },
  {
    id: 'qr-express',
    tabName: t('experiences.qrTab'),
    title: t('experiences.qrTitle'),
    subtitle: t('experiences.qrSub'),
    image: 'https://images.unsplash.com/photo-1526367790999-0150786686a2?w=1200&auto=format&fit=crop&q=80',
    badge: t('experiences.qrBadge'),
    perks: [
      t('experiences.qrP1'),
      t('experiences.qrP2'),
      t('experiences.qrP3'),
      t('experiences.qrP4')
    ],
    ctaText: t('experiences.qrCta'),
    ctaLink: '/menu'
  }
])
const activeExperienceId = ref('dining')
const activeExperience = computed(() => {
  return experiences.value.find(e => e.id === activeExperienceId.value) || experiences.value[0]
})

// ── Animated Counter ─────────────────────────────────────────────────────────
const statsVisible   = ref(false)
const statsRef       = ref(null)
const animatedValues = ref([0, 0, 0, 0])

const statsTargets = computed(() => [
  { target: 98,  suffix: '%', label: t('stats.satisfaction'), sub: t('stats.satisfactionSub') },
  { target: 15,  suffix: '+', label: t('stats.years'), sub: t('stats.yearsSub') },
  { target: 150, suffix: '+', label: t('stats.curated'), sub: t('stats.curatedSub') },
  { target: 24,  suffix: '/7', label: t('stats.roomService'), sub: t('stats.roomServiceSub') },
])

function animateCounters() {
  statsTargets.value.forEach((stat, i) => {
    const duration = 1800
    const steps    = 50
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
    ([entry]) => {
      if (entry.isIntersecting && !statsVisible.value) {
        statsVisible.value = true
        animateCounters()
      }
    },
    { threshold: 0.25 }
  )
  if (statsRef.value) statsObserver.observe(statsRef.value)
})
onUnmounted(() => statsObserver?.disconnect())

// ── Testimonials Carousel ─────────────────────────────────────────────────────
const testimonials = computed(() => [
  {
    name: t('reviews.r1Name'),
    role: t('reviews.r1Role'),
    text: t('reviews.r1Text'),
    stars: 5,
    tag: t('reviews.r1Tag')
  },
  {
    name: t('reviews.r2Name'),
    role: t('reviews.r2Role'),
    text: t('reviews.r2Text'),
    stars: 5,
    tag: t('reviews.r2Tag')
  },
  {
    name: t('reviews.r3Name'),
    role: t('reviews.r3Role'),
    text: t('reviews.r3Text'),
    stars: 5,
    tag: t('reviews.r3Tag')
  },
  {
    name: t('reviews.r4Name'),
    role: t('reviews.r4Role'),
    text: t('reviews.r4Text'),
    stars: 5,
    tag: t('reviews.r4Tag')
  },
  {
    name: t('reviews.r5Name'),
    role: t('reviews.r5Role'),
    text: t('reviews.r5Text'),
    stars: 5,
    tag: t('reviews.r5Tag')
  }
])
const activeTestimonial = ref(0)
let testimonialTimer

function startTestimonialTimer() {
  testimonialTimer = setInterval(() => {
    if (testimonials.value.length > 0) {
      activeTestimonial.value = (activeTestimonial.value + 1) % testimonials.value.length
    }
  }, 5000)
}
function prevTestimonial() {
  clearInterval(testimonialTimer)
  if (testimonials.value.length > 0) {
    activeTestimonial.value = (activeTestimonial.value - 1 + testimonials.value.length) % testimonials.value.length
  }
  startTestimonialTimer()
}
function nextTestimonial() {
  clearInterval(testimonialTimer)
  if (testimonials.value.length > 0) {
    activeTestimonial.value = (activeTestimonial.value + 1) % testimonials.value.length
  }
  startTestimonialTimer()
}
onMounted(startTestimonialTimer)
onUnmounted(() => clearInterval(testimonialTimer))

// ── Interactive FAQ Accordion ────────────────────────────────────────────────
const faqs = computed(() => [
  { question: t('faqs.q1'), answer: t('faqs.a1') },
  { question: t('faqs.q2'), answer: t('faqs.a2') },
  { question: t('faqs.q3'), answer: t('faqs.a3') },
  { question: t('faqs.q4'), answer: t('faqs.a4') },
  { question: t('faqs.q5'), answer: t('faqs.a5') },
])
const openFaq = ref(0)
function toggleFaq(index) {
  openFaq.value = openFaq.value === index ? -1 : index
}

// ── Visual Gallery Lightbox Modal ────────────────────────────────────────────
const galleryImages = [
  {
    title: 'Dry-Aged Angus Ribeye Searing',
    tag: 'Chef Signature',
    url: 'https://images.unsplash.com/photo-1544025162-d76694265947?w=900&auto=format&fit=crop&q=80',
    desc: 'Charred over aromatic hickory coals to golden perfection.'
  },
  {
    title: 'Woodfired Neapolitan Pizza',
    tag: 'Artisanal Italian',
    url: 'https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?w=900&auto=format&fit=crop&q=80',
    desc: '48-hour fermented dough blistered in a 450°C wood oven.'
  },
  {
    title: 'Traditional Ethiopian Buna Ceremony',
    tag: 'Heritage Ritual',
    url: 'https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?w=900&auto=format&fit=crop&q=80',
    desc: 'Single-origin Yirgacheffe coffee beans roasted tableside.'
  },
  {
    title: 'Skyline Terrace Sunset Cocktails',
    tag: 'Sky Lounge',
    url: 'https://images.unsplash.com/photo-1510812431401-41d2bd2722f3?w=900&auto=format&fit=crop&q=80',
    desc: 'Breathtaking golden hour views over Addis Ababa with botanical spritzers.'
  },
  {
    title: 'Presidential Penthouse Suite',
    tag: 'Boutique Suites',
    url: 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=900&auto=format&fit=crop&q=80',
    desc: 'Panoramic terrace, private jacuzzi, and 24/7 in-room dining.'
  },
  {
    title: 'Velvety Italian Tiramisu',
    tag: 'House Dessert',
    url: 'https://images.unsplash.com/photo-1571877227200-a0d98ea607e9?w=900&auto=format&fit=crop&q=80',
    desc: 'Layered mascarpone cream dusted with French Valrhona cocoa.'
  }
]
const activeLightbox = ref(null)

// ── VIP Club Newsletter ──────────────────────────────────────────────────────
const newsletterEmail = ref('')
const newsletterSubscribed = ref(false)

function subscribeNewsletter() {
  if (!newsletterEmail.value || !newsletterEmail.value.includes('@')) {
    ui.toast('Please enter a valid email address', 'warning')
    return
  }
  newsletterSubscribed.value = true
  ui.toast('Welcome to the VIP Club! Your 15% discount voucher is on its way.', 'success')
  newsletterEmail.value = ''
}
</script>

<template>
  <DefaultLayout>

    <!-- ══ HERO SECTION ══════════════════════════════════════════════════════ -->
    <section class="relative min-h-[92vh] flex items-center overflow-hidden bg-warm-950">
      <!-- Background Image with dynamic parallax/zoom -->
      <div class="absolute inset-0">
        <img
          src="https://images.unsplash.com/photo-1544025162-d76694265947?w=1920&auto=format&fit=crop&q=85"
          alt="Luxury dining atmosphere"
          class="w-full h-full object-cover scale-105 transition-transform duration-[12000ms]"
          style="animation: heroZoom 16s ease-in-out infinite alternate"
        />
        <!-- Atmospheric Vignette & Gradients -->
        <div class="absolute inset-0 bg-gradient-to-r from-black/95 via-black/75 to-black/35"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-warm-950 via-transparent to-black/60"></div>
        <!-- Warm ambient glow -->
        <div class="absolute -top-40 left-1/4 w-96 h-96 bg-primary-500/15 rounded-full blur-3xl pointer-events-none"></div>
      </div>

      <div class="page-container relative z-10 py-20 lg:py-28">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">

          <!-- Left Column: Compelling Copy & Calls to Action -->
          <div class="lg:col-span-7 xl:col-span-8 space-y-6">
            <!-- Trust Badge -->
            <div class="inline-flex items-center gap-2.5 px-4 py-1.5 rounded-full bg-white/10 backdrop-blur-md border border-white/20 text-primary-300 text-xs font-semibold tracking-wider uppercase shadow-inner">
              <Sparkles :size="14" class="text-primary-400 animate-pulse" />
              <span>{{ $t('hero.badge') }}</span>
            </div>

            <!-- Headline -->
            <h1 class="font-heading text-4xl sm:text-5xl md:text-6xl lg:text-[4.2rem] font-bold text-white leading-[1.08] tracking-tight">
              {{ $t('hero.titlePart1') }} <br />
              <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary-400 via-primary-300 to-amber-200">
                {{ $t('hero.titleHighlight') }}
              </span> <br />
              {{ $t('hero.titlePart2') }}
            </h1>

            <!-- Subtitle with Real Information -->
            <p class="text-base sm:text-lg text-white/80 max-w-2xl leading-relaxed">
              {{ $t('hero.subtitle') }}
            </p>

            <!-- Key Feature Checkmarks -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 text-sm text-white/85">
              <div class="flex items-center gap-2.5">
                <CheckCircle :size="17" class="text-primary-400 shrink-0" />
                <span>{{ $t('hero.feature1') }}</span>
              </div>
              <div class="flex items-center gap-2.5">
                <CheckCircle :size="17" class="text-primary-400 shrink-0" />
                <span>{{ $t('hero.feature2') }}</span>
              </div>
              <div class="flex items-center gap-2.5">
                <CheckCircle :size="17" class="text-primary-400 shrink-0" />
                <span>{{ $t('hero.feature3') }}</span>
              </div>
              <div class="flex items-center gap-2.5">
                <CheckCircle :size="17" class="text-primary-400 shrink-0" />
                <span>{{ $t('hero.feature4') }}</span>
              </div>
            </div>

            <!-- Primary CTAs -->
            <div class="flex flex-wrap items-center gap-4 pt-4">
              <RouterLink
                to="/menu"
                class="btn-primary text-base px-8 py-3.5 shadow-xl shadow-primary-500/30 hover:scale-[1.02] active:scale-95 transition-all duration-200"
              >
                {{ $t('hero.exploreMenu') }} <ArrowRight :size="18" />
              </RouterLink>

              <RouterLink
                to="/reservations"
                class="inline-flex items-center gap-2 rounded-xl px-7 py-3.5 text-base font-semibold border-2 border-white/30 text-white hover:border-primary-400 hover:bg-white/10 backdrop-blur-sm transition-all duration-200"
              >
                <CalendarCheck :size="18" class="text-primary-400" />
                {{ $t('hero.reserveTable') }}
              </RouterLink>

              <RouterLink
                to="/rooms"
                class="inline-flex items-center gap-2 rounded-xl px-6 py-3.5 text-sm font-semibold text-white/80 hover:text-white hover:underline transition-all duration-200"
              >
                <BedDouble :size="18" class="text-primary-400" />
                {{ $t('hero.hotelSuites') }}
              </RouterLink>
            </div>

            <!-- Live Status & Delivery ETA Pill -->
            <div class="pt-2 flex flex-wrap items-center gap-4 text-xs text-white/70">
              <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-emerald-950/60 border border-emerald-500/40 text-emerald-300 font-medium">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                <span>{{ $t('hero.openNow') }}</span>
              </div>
              <div class="flex items-center gap-1.5 text-warm-300">
                <Clock :size="14" class="text-primary-400" />
                <span>{{ $t('hero.avgPrep') }}</span>
              </div>
              <div class="flex items-center gap-1.5 text-warm-300">
                <Truck :size="14" class="text-primary-400" />
                <span>{{ $t('hero.deliveryEta') }}</span>
              </div>
            </div>
          </div>

          <!-- Right Column: Sizzling Floating Feature Showcase -->
          <div class="lg:col-span-5 xl:col-span-4 relative hidden sm:block">
            <!-- Glass Floating Card 1: Today's Chef Pick -->
            <div class="glass-dark rounded-2xl p-4 border border-white/15 shadow-2xl backdrop-blur-xl relative z-20 max-w-md mx-auto hover:-translate-y-1 transition-transform duration-300">
              <div class="relative rounded-xl overflow-hidden aspect-[16/10] mb-3">
                <img
                  src="https://images.unsplash.com/photo-1558030006-450675393462?w=700&auto=format&fit=crop&q=80"
                  alt="Prime Angus Ribeye"
                  class="w-full h-full object-cover"
                />
                <span class="absolute top-2.5 left-2.5 px-2.5 py-1 rounded-full bg-primary-600/90 text-white text-[11px] font-bold tracking-wider uppercase shadow-md flex items-center gap-1">
                  <Flame :size="12" /> {{ $t('hero.chefSignature') }}
                </span>
                <span class="absolute bottom-2.5 right-2.5 px-2 py-0.5 rounded-lg bg-black/75 text-white text-xs font-semibold backdrop-blur-md">
                  $36.50
                </span>
              </div>
              <div class="flex items-start justify-between gap-2">
                <div>
                  <h4 class="font-heading font-bold text-white text-base leading-snug">{{ $t('hero.ribeyeTitle') }}</h4>
                  <p class="text-white/60 text-xs mt-0.5 line-clamp-1">{{ $t('hero.ribeyeDesc') }}</p>
                </div>
                <div class="flex items-center gap-1 bg-amber-400/20 px-2 py-1 rounded-lg shrink-0">
                  <Star :size="13" class="text-amber-400 fill-amber-400" />
                  <span class="text-amber-300 text-xs font-bold">4.95</span>
                </div>
              </div>
              <div class="mt-3.5 pt-3 border-t border-white/10 flex items-center justify-between">
                <span class="text-[11px] text-white/50">{{ $t('hero.ordersCount', { count: 142 }) }}</span>
                <RouterLink
                  to="/menu/prime-angus-ribeye-steak"
                  class="text-xs font-semibold text-primary-400 hover:text-primary-300 inline-flex items-center gap-1 group"
                >
                  {{ $t('hero.orderDish') }} <ChevronRight :size="14" class="group-hover:translate-x-0.5 transition-transform" />
                </RouterLink>
              </div>
            </div>

            <!-- Glass Floating Card 2: Hotel & Instant QR Ordering -->
            <div class="glass-dark rounded-2xl p-4 border border-white/15 shadow-2xl backdrop-blur-xl relative z-10 max-w-sm ml-auto -mt-8 hover:-translate-y-1 transition-transform duration-300">
              <div class="flex items-center gap-3.5">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-primary-500 to-amber-600 flex items-center justify-center text-white shrink-0 shadow-lg">
                  <BedDouble :size="22" />
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex items-center justify-between">
                    <p class="font-semibold text-white text-sm">{{ $t('hero.suitesCardTitle') }}</p>
                    <span class="text-[10px] uppercase font-bold text-emerald-400 bg-emerald-950/60 px-1.5 py-0.5 rounded">{{ $t('hero.suitesCardBadge') }}</span>
                  </div>
                  <p class="text-xs text-white/60 mt-0.5 truncate">{{ $t('hero.suitesCardSubtitle') }}</p>
                </div>
              </div>
              <div class="mt-3 flex items-center justify-between text-xs pt-2.5 border-t border-white/10">
                <span class="text-white/60">{{ $t('hero.suitesFrom') }} <strong class="text-white">$120</strong>{{ $t('hero.suitesPerNight') }}</span>
                <RouterLink to="/rooms" class="text-primary-400 font-semibold hover:underline">{{ $t('hero.viewSuites') }} &rarr;</RouterLink>
              </div>
            </div>
          </div>

        </div>
      </div>
    </section>

    <!-- ══ QUICK TABLE RESERVATION & BOOKING BAR (Attractive Interactive Feature) ══ -->
    <section class="relative z-20 -mt-8 px-4">
      <div class="max-w-5xl mx-auto">
        <form
          @submit.prevent="submitQuickBooking"
          class="glass-panel rounded-3xl p-5 md:p-6 shadow-2xl border border-warm-200/90 dark:border-white/10 backdrop-blur-md"
        >
          <div class="flex flex-col lg:flex-row items-stretch lg:items-center gap-4">

            <!-- Title icon indicator -->
            <div class="hidden lg:flex items-center gap-3 pr-4 border-r border-warm-200 dark:border-warm-700">
              <div class="w-11 h-11 rounded-2xl bg-primary-50 dark:bg-primary-900/30 flex items-center justify-center text-primary-500 shrink-0">
                <CalendarCheck :size="22" />
              </div>
              <div class="text-left">
                <p class="text-xs uppercase font-bold tracking-wider text-primary-600 dark:text-primary-400">{{ $t('booking.quickBook') }}</p>
                <p class="text-sm font-bold text-warm-900 dark:text-white leading-tight">{{ $t('booking.tableDining') }}</p>
              </div>
            </div>

            <!-- Guests -->
            <div class="flex-1">
              <label class="block text-xs font-semibold text-warm-500 dark:text-warm-400 mb-1 flex items-center gap-1.5">
                <Users :size="13" class="text-primary-500" /> {{ $t('booking.guests') }}
              </label>
              <select
                v-model.number="quickBooking.partySize"
                class="w-full bg-white dark:bg-gray-900 border border-warm-300 dark:border-warm-700 rounded-xl px-3.5 py-2.5 text-sm font-semibold text-warm-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500 shadow-2xs cursor-pointer"
              >
                <option :value="1">{{ $t('booking.solo') }}</option>
                <option :value="2">{{ $t('booking.couple') }}</option>
                <option :value="4">{{ $t('booking.family') }}</option>
                <option :value="6">{{ $t('booking.party') }}</option>
                <option :value="8">{{ $t('booking.banquet') }}</option>
              </select>
            </div>

            <!-- Date Option -->
            <div class="flex-1">
              <label class="block text-xs font-semibold text-warm-500 dark:text-warm-400 mb-1 flex items-center gap-1.5">
                <Calendar :size="13" class="text-primary-500" /> {{ $t('booking.when') }}
              </label>
              <select
                v-model="quickBooking.dateOption"
                class="w-full bg-white dark:bg-gray-900 border border-warm-300 dark:border-warm-700 rounded-xl px-3.5 py-2.5 text-sm font-semibold text-warm-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500 shadow-2xs cursor-pointer"
              >
                <option value="tonight">{{ $t('booking.tonight') }}</option>
                <option value="tomorrow">{{ $t('booking.tomorrow') }}</option>
                <option value="weekend">{{ $t('booking.weekend') }}</option>
              </select>
            </div>

            <!-- Preferred Time -->
            <div class="flex-1">
              <label class="block text-xs font-semibold text-warm-500 dark:text-warm-400 mb-1 flex items-center gap-1.5">
                <Clock :size="13" class="text-primary-500" /> {{ $t('booking.timeSlot') }}
              </label>
              <select
                v-model="quickBooking.timeSlot"
                class="w-full bg-white dark:bg-gray-900 border border-warm-300 dark:border-warm-700 rounded-xl px-3.5 py-2.5 text-sm font-semibold text-warm-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500 shadow-2xs cursor-pointer"
              >
                <option value="12:30">{{ $t('booking.timeLunch') }}</option>
                <option value="14:00">{{ $t('booking.timeLateLunch') }}</option>
                <option value="18:30">{{ $t('booking.timeSunset') }}</option>
                <option value="19:30">{{ $t('booking.timeDinner') }}</option>
                <option value="20:30">{{ $t('booking.timeEvening') }}</option>
                <option value="21:30">{{ $t('booking.timeLateLounge') }}</option>
              </select>
            </div>

            <!-- Seating Zone -->
            <div class="flex-1">
              <label class="block text-xs font-semibold text-warm-500 dark:text-warm-400 mb-1 flex items-center gap-1.5">
                <Wine :size="13" class="text-primary-500" /> {{ $t('booking.seatingArea') }}
              </label>
              <select
                v-model="quickBooking.seatingZone"
                class="w-full bg-white dark:bg-gray-900 border border-warm-300 dark:border-warm-700 rounded-xl px-3.5 py-2.5 text-sm font-semibold text-warm-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500 shadow-2xs cursor-pointer"
              >
                <option value="Main Dining Hall">{{ $t('booking.zoneMain') }}</option>
                <option value="Sky Garden Terrace">{{ $t('booking.zoneTerrace') }}</option>
                <option value="Private VIP Wine Cellar">{{ $t('booking.zoneCellar') }}</option>
                <option value="Boutique Suite Balcony">{{ $t('booking.zoneBalcony') }}</option>
              </select>
            </div>

            <!-- Submit Action -->
            <div class="pt-2 lg:pt-0 shrink-0">
              <button
                type="submit"
                class="w-full lg:w-auto btn-primary px-7 py-3 text-sm font-bold shadow-lg shadow-primary-500/25 hover:shadow-primary-500/40"
              >
                {{ $t('booking.findTable') }} <ArrowRight :size="16" />
              </button>
            </div>

          </div>
        </form>
      </div>
    </section>

    <!-- ══ PROMOTIONAL VOUCHER BANNER (Interactive Copy Code) ═════════════════ -->
    <section class="pt-12 pb-6">
      <div class="page-container">
        <div class="relative rounded-3xl overflow-hidden p-6 sm:p-8 bg-gradient-to-r from-warm-900 via-primary-950 to-warm-900 border border-primary-500/30 text-white shadow-xl">
          <div class="absolute -right-12 -bottom-12 w-64 h-64 bg-primary-500/10 rounded-full blur-2xl pointer-events-none"></div>

          <div class="relative z-10 flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="flex items-center gap-4 text-center md:text-left">
              <div class="w-14 h-14 rounded-2xl bg-primary-500/20 border border-primary-500/40 flex items-center justify-center text-primary-400 shrink-0 shadow-inner">
                <Percent :size="28" />
              </div>
              <div>
                <div class="flex items-center justify-center md:justify-start gap-2 mb-1">
                  <span class="px-2.5 py-0.5 rounded-full bg-primary-500/30 text-primary-300 text-[11px] font-bold uppercase tracking-wider">{{ $t('promo.badge') }}</span>
                  <span class="text-xs text-white/50">• {{ $t('promo.limitedTime') }}</span>
                </div>
                <h3 class="font-heading font-bold text-xl sm:text-2xl text-white">{{ $t('promo.title') }}</h3>
                <p class="text-xs sm:text-sm text-white/70 mt-1 max-w-xl">
                  {{ $t('promo.subtitle') }}
                </p>
              </div>
            </div>

            <!-- Coupon Code Box -->
            <div class="flex items-center gap-3 shrink-0">
              <div class="bg-black/40 border border-white/20 rounded-2xl px-5 py-2.5 flex items-center gap-3">
                <span class="font-mono text-base font-bold text-primary-300 tracking-wider">{{ promoCode }}</span>
                <button
                  type="button"
                  @click="copyPromoCode"
                  class="btn-ghost !px-2.5 !py-1 text-xs text-white/80 hover:text-white flex items-center gap-1 rounded-lg bg-white/10"
                  :title="'Copy code ' + promoCode"
                >
                  <component :is="promoCopied ? Check : Copy" :size="14" :class="promoCopied ? 'text-emerald-400' : ''" />
                  <span>{{ promoCopied ? $t('promo.copied') : $t('promo.copyCode') }}</span>
                </button>
              </div>
              <RouterLink to="/menu" class="btn-primary text-xs font-bold py-2.5 px-5">
                {{ $t('promo.orderNow') }}
              </RouterLink>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ══ DIETARY & EXCELLENCE TICKER ════════════════════════════════════════ -->
    <section class="bg-primary-600 dark:bg-primary-700 py-3.5 overflow-hidden">
      <div class="page-container">
        <div class="flex flex-wrap items-center justify-center gap-8 md:gap-14 text-white/95 text-xs sm:text-sm font-semibold tracking-wide">
          <div class="flex items-center gap-2">
            <Award :size="16" />
            <span>{{ $t('ticker.halal') }}</span>
          </div>
          <div class="flex items-center gap-2">
            <Leaf :size="16" />
            <span>{{ $t('ticker.fasting') }}</span>
          </div>
          <div class="flex items-center gap-2">
            <ShieldCheck :size="16" />
            <span>{{ $t('ticker.organic') }}</span>
          </div>
          <div class="flex items-center gap-2">
            <Truck :size="16" />
            <span>{{ $t('ticker.delivery') }}</span>
          </div>
          <div class="flex items-center gap-2">
            <BedDouble :size="16" />
            <span>{{ $t('ticker.roomDining') }}</span>
          </div>
        </div>
      </div>
    </section>

    <!-- ══ ANIMATED VERIFIED STATS ════════════════════════════════════════════ -->
    <section ref="statsRef" class="py-16 bg-warm-950 dark:bg-black text-white">
      <div class="page-container">
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-8">
          <div
            v-for="(stat, i) in statsTargets"
            :key="stat.label"
            class="text-center group border-r last:border-none border-white/10 px-4"
          >
            <div class="inline-flex items-end gap-1 mb-2">
              <span class="font-heading text-4xl sm:text-5xl font-extrabold text-primary-400 tabular-nums leading-none">
                {{ statsVisible ? animatedValues[i] : 0 }}
              </span>
              <span class="font-heading text-2xl font-bold text-amber-300 mb-1">{{ stat.suffix }}</span>
            </div>
            <h4 class="text-sm font-bold text-white tracking-wide uppercase">{{ stat.label }}</h4>
            <p class="text-xs text-warm-400 mt-1 max-w-[180px] mx-auto leading-relaxed">{{ stat.sub }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ══ INTERACTIVE 4-WAY EXPERIENCE SHOWCASE (Attractive Feature) ═════════ -->
    <section class="py-24 bg-warm-50 dark:bg-warm-900/40">
      <div class="page-container">
        <div class="text-center max-w-2xl mx-auto mb-12">
          <div class="flex items-center justify-center gap-2 mb-3">
            <div class="h-px w-8 bg-primary-500"></div>
            <span class="text-primary-500 font-bold text-xs uppercase tracking-[0.2em]">{{ $t('experienceSection.tag') }}</span>
            <div class="h-px w-8 bg-primary-500"></div>
          </div>
          <h2 class="font-heading text-3xl md:text-4xl lg:text-5xl font-bold text-warm-900 dark:text-white mb-4">
            {{ $t('experienceSection.title') }}
          </h2>
          <p class="text-warm-600 dark:text-warm-300 text-base">
            {{ $t('experienceSection.subtitle') }}
          </p>
        </div>

        <!-- Experience Tab Switcher -->
        <div class="flex flex-wrap items-center justify-center gap-2 sm:gap-3 mb-10">
          <button
            v-for="exp in experiences"
            :key="exp.id"
            @click="activeExperienceId = exp.id"
            :class="[
              'px-5 py-2.5 rounded-2xl text-xs sm:text-sm font-bold transition-all duration-300 shadow-sm cursor-pointer',
              activeExperienceId === exp.id
                ? 'bg-primary-500 text-white shadow-primary-500/25 shadow-md scale-105'
                : 'bg-white dark:bg-gray-900 border border-warm-200 dark:border-warm-800 text-warm-700 dark:text-warm-300 hover:border-primary-400 hover:text-primary-600 dark:hover:text-primary-400 shadow-2xs'
            ]"
          >
            {{ exp.tabName }}
          </button>
        </div>

        <!-- Active Experience Dynamic Showcase Card -->
        <div class="card-glass overflow-hidden shadow-2xl transition-all duration-500">
          <div class="grid grid-cols-1 lg:grid-cols-12 items-center">

            <!-- Visual Image Preview -->
            <div class="lg:col-span-7 relative overflow-hidden min-h-[340px] sm:min-h-[440px] group">
              <img
                :src="activeExperience.image"
                :alt="activeExperience.title"
                class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105"
              />
              <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/25 to-transparent"></div>
              <div class="absolute top-4 left-4">
                <span class="px-3 py-1 rounded-full bg-black/60 text-white backdrop-blur-md text-xs font-semibold uppercase tracking-wider border border-white/20">
                  {{ activeExperience.badge }}
                </span>
              </div>
            </div>

            <!-- Content Details -->
            <div class="lg:col-span-5 p-8 sm:p-10 lg:p-12 space-y-6">
              <div>
                <span class="text-primary-600 dark:text-primary-400 font-bold text-xs uppercase tracking-widest">{{ activeExperience.tabName }}</span>
                <h3 class="font-heading text-2xl sm:text-3xl font-bold text-warm-900 dark:text-white mt-1 leading-snug">
                  {{ activeExperience.title }}
                </h3>
                <p class="text-sm text-warm-600 dark:text-warm-300 mt-3 leading-relaxed">
                  {{ activeExperience.subtitle }}
                </p>
              </div>

              <!-- Perks List -->
              <ul class="space-y-3">
                <li
                  v-for="(perk, i) in activeExperience.perks"
                  :key="i"
                  class="flex items-start gap-3 text-xs sm:text-sm text-warm-700 dark:text-warm-200"
                >
                  <CheckCircle :size="16" class="text-primary-500 shrink-0 mt-0.5" />
                  <span>{{ perk }}</span>
                </li>
              </ul>

              <!-- Action Link -->
              <div class="pt-2">
                <RouterLink
                  :to="activeExperience.ctaLink"
                  class="btn-primary w-full sm:w-auto text-sm font-bold px-7 py-3 shadow-md"
                >
                  {{ activeExperience.ctaText }} <ArrowRight :size="16" />
                </RouterLink>
              </div>
            </div>

          </div>
        </div>
      </div>
    </section>

    <!-- ══ INTERACTIVE CULINARY SHOWCASE & MENU PREVIEW ═══════════════════════ -->
    <section class="py-24 page-container">
      <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-12">
        <div>
          <div class="flex items-center gap-2 mb-2">
            <div class="h-px w-8 bg-primary-500"></div>
            <span class="text-primary-500 font-bold text-xs uppercase tracking-[0.2em]">{{ $t('culinary.badge') }}</span>
          </div>
          <h2 class="font-heading text-3xl md:text-4xl lg:text-5xl font-bold text-warm-900 dark:text-white">
            {{ $t('culinary.title') }}
          </h2>
          <p class="text-warm-500 dark:text-warm-400 text-sm mt-2 max-w-xl">
            {{ $t('culinary.subtitle') }}
          </p>
        </div>

        <RouterLink
          to="/menu"
          class="hidden sm:inline-flex items-center gap-2 text-sm font-bold text-primary-600 dark:text-primary-400 hover:gap-3 transition-all"
        >
          {{ $t('culinary.viewComplete') }} <ArrowRight :size="16" />
        </RouterLink>
      </div>

      <!-- Category Filter Pills (Interactive feature) -->
      <div class="flex gap-2.5 overflow-x-auto pb-4 mb-8 -mx-4 px-4 scrollbar-hide">
        <button
          v-for="tab in menuTabs"
          :key="tab.id"
          @click="activeMenuTab = tab.id"
          :class="[
            'px-5 py-2.5 rounded-full text-xs font-bold whitespace-nowrap transition-all duration-200 cursor-pointer',
            activeMenuTab === tab.id
              ? 'bg-primary-500 text-white shadow-md shadow-primary-500/25'
              : 'bg-warm-100 dark:bg-warm-800 text-warm-700 dark:text-warm-300 hover:bg-warm-200 dark:hover:bg-warm-700'
          ]"
        >
          {{ tab.label }}
        </button>
      </div>

      <!-- Dish Cards Grid -->
      <div v-if="featuredLoading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <DishCardSkeleton v-for="i in 4" :key="i" />
      </div>

      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <DishCard
          v-for="dish in filteredDishes"
          :key="dish.id"
          :dish="dish"
        />
      </div>

      <!-- Mobile Button to Menu -->
      <div class="mt-10 text-center sm:hidden">
        <RouterLink to="/menu" class="btn-primary w-full justify-center">
          {{ $t('culinary.browseMobile') }} <ArrowRight :size="16" />
        </RouterLink>
      </div>
    </section>

    <!-- ══ AUTHENTIC ETHIOPIAN COFFEE CEREMONY & CULTURAL CORNER ══════════════ -->
    <section class="py-20 bg-warm-900 text-white relative overflow-hidden">
      <!-- Ambient coffee steam image background -->
      <div class="absolute inset-0 opacity-20">
        <img
          src="https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?w=1600&auto=format&fit=crop&q=80"
          alt="Ethiopian coffee ceremony"
          class="w-full h-full object-cover"
        />
      </div>
      <div class="absolute -left-20 top-0 w-80 h-80 bg-primary-600/20 rounded-full blur-3xl"></div>

      <div class="page-container relative z-10">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-center">

          <!-- Left: High definition ceremony image -->
          <div class="lg:col-span-5 relative">
            <div class="rounded-3xl overflow-hidden shadow-2xl border-2 border-white/15 aspect-[4/3] group">
              <img
                src="https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?w=800&auto=format&fit=crop&q=80"
                alt="Clay Jebena coffee brewing"
                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700"
              />
              <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent"></div>
              <div class="absolute bottom-4 left-4 right-4 text-xs text-white/90">
                <span class="font-bold text-amber-300">{{ $t('buna.jebenaTag') }}</span> {{ $t('buna.jebenaDesc') }}
              </div>
            </div>
          </div>

          <!-- Right: Authentic Cultural Story -->
          <div class="lg:col-span-7 space-y-5">
            <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-primary-500/20 border border-primary-500/40 text-primary-300 text-xs font-bold uppercase tracking-wider">
              <Coffee :size="14" /> {{ $t('buna.badge') }}
            </div>

            <h3 class="font-heading text-3xl sm:text-4xl font-bold text-white leading-tight">
              {{ $t('buna.title') }} <br />
              <span class="text-primary-400">{{ $t('buna.highlight') }}</span>
            </h3>

            <p class="text-white/80 text-sm sm:text-base leading-relaxed">
              {{ $t('buna.subtitle') }}
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2">
              <div class="p-4 rounded-2xl bg-white/5 border border-white/10">
                <span class="font-bold text-amber-300 text-sm block mb-1">{{ $t('buna.step1Title') }}</span>
                <p class="text-xs text-white/70">{{ $t('buna.step1Desc') }}</p>
              </div>
              <div class="p-4 rounded-2xl bg-white/5 border border-white/10">
                <span class="font-bold text-amber-300 text-sm block mb-1">{{ $t('buna.step2Title') }}</span>
                <p class="text-xs text-white/70">{{ $t('buna.step2Desc') }}</p>
              </div>
              <div class="p-4 rounded-2xl bg-white/5 border border-white/10">
                <span class="font-bold text-amber-300 text-sm block mb-1">{{ $t('buna.step3Title') }}</span>
                <p class="text-xs text-white/70">{{ $t('buna.step3Desc') }}</p>
              </div>
            </div>

            <div class="pt-2 flex flex-wrap items-center gap-4 text-xs text-amber-300/90">
              <span class="font-semibold">{{ $t('buna.complimentary') }}</span>
            </div>
          </div>

        </div>
      </div>
    </section>

    <!-- ══ EXECUTIVE CHEF SPOTLIGHT & PHILOSOPHY ══════════════════════════════ -->
    <section class="py-24 page-container">
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">

        <!-- Chef Copy -->
        <div class="lg:col-span-6 space-y-6">
          <div class="flex items-center gap-2">
            <div class="h-px w-8 bg-primary-500"></div>
            <span class="text-primary-500 font-bold text-xs uppercase tracking-[0.2em]">{{ $t('chef.leadership') }}</span>
          </div>

          <h2 class="font-heading text-3xl sm:text-4xl lg:text-5xl font-bold text-warm-900 dark:text-white leading-tight">
            {{ $t('chef.quote') }}
          </h2>

          <p class="text-warm-600 dark:text-warm-300 text-base leading-relaxed">
            {{ $t('chef.bio') }}
          </p>

          <div class="space-y-4 pt-2">
            <div class="flex items-start gap-4">
              <div class="w-10 h-10 rounded-xl bg-primary-50 dark:bg-primary-900/30 flex items-center justify-center text-primary-500 shrink-0">
                <Award :size="20" />
              </div>
              <div>
                <h4 class="font-heading font-bold text-warm-900 dark:text-white text-sm">{{ $t('chef.freshnessTitle') }}</h4>
                <p class="text-xs text-warm-500 dark:text-warm-400 mt-0.5">{{ $t('chef.freshnessDesc') }}</p>
              </div>
            </div>

            <div class="flex items-start gap-4">
              <div class="w-10 h-10 rounded-xl bg-primary-50 dark:bg-primary-900/30 flex items-center justify-center text-primary-500 shrink-0">
                <UtensilsCrossed :size="20" />
              </div>
              <div>
                <h4 class="font-heading font-bold text-warm-900 dark:text-white text-sm">{{ $t('chef.handcraftedTitle') }}</h4>
                <p class="text-xs text-warm-500 dark:text-warm-400 mt-0.5">{{ $t('chef.handcraftedDesc') }}</p>
              </div>
            </div>

            <div class="flex items-start gap-4">
              <div class="w-10 h-10 rounded-xl bg-primary-50 dark:bg-primary-900/30 flex items-center justify-center text-primary-500 shrink-0">
                <ShieldCheck :size="20" />
              </div>
              <div>
                <h4 class="font-heading font-bold text-warm-900 dark:text-white text-sm">{{ $t('chef.trustTitle') }}</h4>
                <p class="text-xs text-warm-500 dark:text-warm-400 mt-0.5">{{ $t('chef.trustDesc') }}</p>
              </div>
            </div>
          </div>

          <div class="pt-4 flex items-center gap-4 border-t border-warm-200 dark:border-warm-700">
            <div>
              <p class="font-heading font-bold text-warm-900 dark:text-white text-base">Chef Marcus Vance &amp; Liya Bekele</p>
              <p class="text-xs text-primary-600 dark:text-primary-400 font-semibold">{{ $t('chef.directors') }}</p>
            </div>
            <div class="ml-auto flex items-center gap-1 text-amber-500">
              <Star v-for="i in 5" :key="i" :size="15" class="fill-amber-400" />
            </div>
          </div>
        </div>

        <!-- Chef Image Card -->
        <div class="lg:col-span-6 relative">
          <div class="relative rounded-3xl overflow-hidden shadow-2xl aspect-[4/5] max-w-lg mx-auto border-4 border-white dark:border-warm-800">
            <img
              src="https://images.unsplash.com/photo-1577219491135-ce391730fb2c?w=900&auto=format&fit=crop&q=80"
              alt="Executive Chef preparing gourmet dish"
              class="w-full h-full object-cover"
            />
            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent"></div>

            <div class="absolute bottom-6 left-6 right-6 p-5 rounded-2xl bg-black/60 backdrop-blur-md border border-white/20 text-white">
              <p class="text-xs text-amber-300 font-bold uppercase tracking-wider">{{ $t('chef.philosophyTag') }}</p>
              <p class="text-sm italic text-white/90 mt-1">{{ $t('chef.philosophyQuote') }}</p>
            </div>
          </div>
        </div>

      </div>
    </section>

    <!-- ══ VISUAL CULINARY GALLERY & INSTAGRAM SHOWCASE (Attractive Feature) ══ -->
    <section class="py-24 bg-warm-50 dark:bg-warm-950/60">
      <div class="page-container">
        <div class="text-center max-w-xl mx-auto mb-14">
          <div class="flex items-center justify-center gap-2 mb-3">
            <div class="h-px w-8 bg-primary-500"></div>
            <span class="text-primary-500 font-bold text-xs uppercase tracking-[0.2em]">{{ $t('gallery.badge') }}</span>
            <div class="h-px w-8 bg-primary-500"></div>
          </div>
          <h2 class="font-heading text-3xl md:text-4xl font-bold text-warm-900 dark:text-white mb-3">
            {{ $t('gallery.title', { brand: brandName }) }}
          </h2>
          <p class="text-warm-500 dark:text-warm-400 text-sm">
            {{ $t('gallery.followUs') }} <strong class="text-primary-600 dark:text-primary-400">@tasteandstay_addis</strong>
          </p>
        </div>

        <!-- 6-Image Showcase Grid -->
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4 sm:gap-6">
          <div
            v-for="(img, idx) in galleryImages"
            :key="idx"
            @click="activeLightbox = img"
            class="group relative overflow-hidden rounded-2xl aspect-square shadow-md bg-warm-200 dark:bg-warm-800 cursor-pointer"
          >
            <img
              :src="img.url"
              :alt="img.title"
              class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
            />
            <!-- Overlay on hover -->
            <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/30 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex flex-col justify-end p-4 text-white">
              <span class="text-[10px] font-bold uppercase tracking-widest text-primary-300">{{ img.tag }}</span>
              <h4 class="font-heading font-bold text-sm sm:text-base leading-snug">{{ img.title }}</h4>
              <p class="text-[11px] text-white/70 mt-0.5 line-clamp-1">{{ img.desc }}</p>
            </div>
          </div>
        </div>

        <!-- Lightbox Modal -->
        <Transition name="fade">
          <div
            v-if="activeLightbox"
            @click.self="activeLightbox = null"
            class="fixed inset-0 z-50 bg-black/90 backdrop-blur-md flex items-center justify-center p-4"
          >
            <div class="relative max-w-2xl w-full bg-warm-900 rounded-3xl overflow-hidden border border-white/20 shadow-2xl">
              <button
                @click="activeLightbox = null"
                class="absolute top-4 right-4 z-10 w-9 h-9 rounded-full bg-black/60 text-white hover:bg-black flex items-center justify-center"
              >
                <X :size="20" />
              </button>

              <img :src="activeLightbox.url" :alt="activeLightbox.title" class="w-full max-h-[60vh] object-cover" />

              <div class="p-6 text-white space-y-2">
                <span class="text-xs font-bold uppercase tracking-wider text-primary-400">{{ activeLightbox.tag }}</span>
                <h3 class="font-heading font-bold text-2xl">{{ activeLightbox.title }}</h3>
                <p class="text-sm text-white/75">{{ activeLightbox.desc }}</p>
                <div class="pt-4 flex items-center gap-3">
                  <RouterLink to="/menu" @click="activeLightbox = null" class="btn-primary text-xs py-2 px-5">
                    {{ $t('gallery.orderFromMenu') }}
                  </RouterLink>
                  <RouterLink to="/reservations" @click="activeLightbox = null" class="btn-outline text-xs py-2 px-5 text-white border-white/40">
                    {{ $t('gallery.reserveTable') }}
                  </RouterLink>
                </div>
              </div>
            </div>
          </div>
        </Transition>
      </div>
    </section>

    <!-- ══ TESTIMONIALS CAROUSEL ═════════════════════════════════════════════ -->
    <section class="py-24 bg-warm-950 dark:bg-black text-white overflow-hidden">
      <div class="page-container">
        <div class="text-center mb-14">
          <div class="flex items-center justify-center gap-2 mb-3">
            <div class="h-px w-8 bg-primary-500"></div>
            <span class="text-primary-500 font-bold text-xs uppercase tracking-[0.2em]">{{ $t('reviews.badge') }}</span>
            <div class="h-px w-8 bg-primary-500"></div>
          </div>
          <h2 class="font-heading text-3xl md:text-4xl lg:text-5xl font-bold text-white mb-3">
            {{ $t('reviews.title') }}
          </h2>
          <p class="text-warm-400 text-sm max-w-md mx-auto">
            {{ $t('reviews.subtitle') }}
          </p>
        </div>

        <!-- Carousel Frame -->
        <div class="relative max-w-3xl mx-auto">
          <Transition name="fade-slide" mode="out-in">
            <div :key="activeTestimonial" class="card-glass bg-white/95 dark:bg-warm-900/95 backdrop-blur-md border border-warm-200/80 dark:border-white/10 p-8 sm:p-12 text-center relative shadow-2xl">
              <!-- Rating Stars -->
              <div class="flex justify-center gap-1.5 mb-6">
                <Star v-for="i in testimonials[activeTestimonial].stars" :key="i" :size="20" class="text-amber-400 fill-amber-400" />
              </div>

              <!-- Quote Text -->
              <p class="text-white/90 text-base sm:text-lg lg:text-xl leading-relaxed mb-8 italic max-w-2xl mx-auto">
                "{{ testimonials[activeTestimonial].text }}"
              </p>

              <!-- Author Info -->
              <div class="flex items-center justify-center gap-3.5">
                <div class="w-12 h-12 rounded-full bg-gradient-to-tr from-primary-600 to-amber-500 flex items-center justify-center font-bold text-white text-base shadow-md">
                  {{ testimonials[activeTestimonial].name[0] }}
                </div>
                <div class="text-left">
                  <p class="font-heading font-bold text-white text-base leading-tight">{{ testimonials[activeTestimonial].name }}</p>
                  <div class="flex items-center gap-2 mt-0.5">
                    <span class="text-xs text-warm-400">{{ testimonials[activeTestimonial].role }}</span>
                    <span class="w-1 h-1 rounded-full bg-primary-400"></span>
                    <span class="text-[10px] text-primary-300 font-semibold uppercase tracking-wider">{{ testimonials[activeTestimonial].tag }}</span>
                  </div>
                </div>
              </div>
            </div>
          </Transition>

          <!-- Nav Buttons -->
          <button
            @click="prevTestimonial"
            class="absolute -left-4 sm:-left-6 top-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-warm-800 hover:bg-primary-500 flex items-center justify-center text-white transition-all duration-200 shadow-xl border border-white/10"
            aria-label="Previous guest review"
          >
            <ChevronLeft :size="20" />
          </button>
          <button
            @click="nextTestimonial"
            class="absolute -right-4 sm:-right-6 top-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-warm-800 hover:bg-primary-500 flex items-center justify-center text-white transition-all duration-200 shadow-xl border border-white/10"
            aria-label="Next guest review"
          >
            <ChevronRight :size="20" />
          </button>

          <!-- Indicator Dots -->
          <div class="flex justify-center gap-2 mt-8">
            <button
              v-for="(_, i) in testimonials"
              :key="i"
              @click="activeTestimonial = i"
              :class="['h-2 rounded-full transition-all duration-300', i === activeTestimonial ? 'bg-primary-500 w-8' : 'bg-warm-700 w-2 hover:bg-warm-600']"
              :aria-label="`Go to review ${i + 1}`"
            ></button>
          </div>
        </div>
      </div>
    </section>

    <!-- ══ FREQUENTLY ASKED QUESTIONS (Interactive Feature) ═══════════════════ -->
    <section class="py-24 page-container max-w-4xl">
      <div class="text-center mb-14">
        <div class="flex items-center justify-center gap-2 mb-3">
          <div class="h-px w-8 bg-primary-500"></div>
          <span class="text-primary-500 font-bold text-xs uppercase tracking-[0.2em]">{{ $t('faqs.badge') }}</span>
          <div class="h-px w-8 bg-primary-500"></div>
        </div>
        <h2 class="font-heading text-3xl md:text-4xl font-bold text-warm-900 dark:text-white mb-3">
          {{ $t('faqs.title') }}
        </h2>
        <p class="text-warm-500 dark:text-warm-400 text-sm">
          {{ $t('faqs.subtitle') }}
        </p>
      </div>

      <div class="space-y-4">
        <div
          v-for="(faq, i) in faqs"
          :key="i"
          class="rounded-2xl border border-warm-200/90 dark:border-white/10 bg-white/95 dark:bg-gray-900/95 backdrop-blur-sm overflow-hidden transition-all duration-200 shadow-xs hover:border-primary-400/40"
        >
          <button
            type="button"
            @click="toggleFaq(i)"
            class="w-full text-left px-6 py-5 flex items-center justify-between gap-4 font-heading font-bold text-warm-900 dark:text-white text-base hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
          >
            <span>{{ faq.question }}</span>
            <ChevronRight
              :size="18"
              class="shrink-0 text-primary-500 transition-transform duration-300"
              :class="{ 'rotate-90': openFaq === i }"
            />
          </button>
          <div
            v-show="openFaq === i"
            class="px-6 pb-5 text-sm text-warm-600 dark:text-warm-300 leading-relaxed border-t border-warm-100 dark:border-warm-700/50 pt-3"
          >
            {{ faq.answer }}
          </div>
        </div>
      </div>
    </section>

    <!-- ══ VIP CONNOISSEUR CLUB NEWSLETTER (Interactive Feature) ═══════════════ -->
    <section class="py-16 bg-gradient-to-br from-primary-900 via-warm-950 to-warm-950 text-white border-y border-white/10">
      <div class="page-container">
        <div class="max-w-2xl mx-auto text-center space-y-4">
          <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary-500/20 text-primary-300 text-xs font-bold uppercase tracking-wider border border-primary-500/40">
            <Sparkles :size="14" /> {{ $t('club.badge') }}
          </div>
          <h3 class="font-heading text-3xl sm:text-4xl font-bold text-white">
            {{ $t('club.title') }}
          </h3>
          <p class="text-sm text-white/75 leading-relaxed">
            {{ $t('club.subtitle') }}
          </p>

          <form @submit.prevent="subscribeNewsletter" class="flex flex-col sm:flex-row gap-3 pt-4 max-w-md mx-auto">
            <input
              v-model="newsletterEmail"
              type="email"
              required
              :placeholder="$t('club.placeholder')"
              class="flex-1 bg-white/10 border border-white/25 rounded-xl px-4 py-3 text-sm text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-primary-400"
            />
            <button
              type="submit"
              class="btn-primary text-sm font-bold px-6 py-3 whitespace-nowrap shadow-lg shadow-primary-500/40"
            >
              {{ $t('club.joinBtn') }}
            </button>
          </form>
          <p class="text-[11px] text-white/50">{{ $t('club.zeroSpam') }}</p>
        </div>
      </div>
    </section>

    <!-- ══ REAL CONTACT, LOCATION & HOURS DETAILS ═════════════════════════════ -->
    <section class="py-16 bg-white dark:bg-gray-950 border-y border-warm-200/80 dark:border-white/10">
      <div class="page-container">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">

          <!-- Phone & Reservations -->
          <div class="flex items-start gap-4">
            <div class="w-12 h-12 rounded-2xl bg-primary-50 dark:bg-primary-900/30 border border-primary-200 dark:border-primary-800 flex items-center justify-center shrink-0 text-primary-600 dark:text-primary-400">
              <Phone :size="22" />
            </div>
            <div>
              <p class="text-xs font-bold uppercase tracking-wider text-warm-500 dark:text-warm-400 mb-0.5">{{ $t('contact.directReservations') }}</p>
              <a :href="`tel:${phoneReservations}`" class="font-heading font-bold text-lg text-warm-900 dark:text-white hover:text-primary-500 transition-colors">
                {{ phoneReservations }}
              </a>
              <p class="text-xs text-warm-500 dark:text-warm-400 mt-0.5">{{ $t('contact.frontDesk') }} {{ phoneFrontDesk }}</p>
            </div>
          </div>

          <!-- Physical Location -->
          <div class="flex items-start gap-4">
            <div class="w-12 h-12 rounded-2xl bg-primary-50 dark:bg-primary-900/30 border border-primary-200 dark:border-primary-800 flex items-center justify-center shrink-0 text-primary-600 dark:text-primary-400">
              <MapPin :size="22" />
            </div>
            <div>
              <p class="text-xs font-bold uppercase tracking-wider text-warm-500 dark:text-warm-400 mb-0.5">{{ $t('contact.ourLocation') }}</p>
              <p class="font-heading font-bold text-warm-900 dark:text-white">{{ locationAddress }}</p>
              <p class="text-xs text-warm-500 dark:text-warm-400 mt-0.5">{{ $t('contact.landmark') }}</p>
            </div>
          </div>

          <!-- Operating Hours -->
          <div class="flex items-start gap-4">
            <div class="w-12 h-12 rounded-2xl bg-primary-50 dark:bg-primary-900/30 border border-primary-200 dark:border-primary-800 flex items-center justify-center shrink-0 text-primary-600 dark:text-primary-400">
              <Clock :size="22" />
            </div>
            <div>
              <p class="text-xs font-bold uppercase tracking-wider text-warm-500 dark:text-warm-400 mb-0.5">{{ $t('contact.hoursTitle') }}</p>
              <p class="font-heading font-bold text-warm-900 dark:text-white">{{ $t('contact.hoursDaily') }}</p>
              <p class="text-xs text-warm-500 dark:text-warm-400 mt-0.5">{{ $t('contact.roomService247') }}</p>
            </div>
          </div>

        </div>
      </div>
    </section>

    <!-- ══ FINAL CALL TO ACTION ═══════════════════════════════════════════════ -->
    <section class="relative overflow-hidden py-28 bg-warm-950">
      <img
        src="https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?w=1600&auto=format&fit=crop&q=80"
        alt="Dining ambiance"
        class="absolute inset-0 w-full h-full object-cover opacity-35"
      />
      <div class="absolute inset-0 bg-gradient-to-t from-warm-950 via-warm-950/70 to-warm-950"></div>

      <div class="page-container relative z-10 text-center max-w-3xl mx-auto space-y-6">
        <div class="flex items-center justify-center gap-2 mb-2">
          <div class="h-px w-8 bg-primary-400"></div>
          <span class="text-primary-400 font-bold text-xs uppercase tracking-[0.2em]">{{ $t('finalCta.badge') }}</span>
          <div class="h-px w-8 bg-primary-400"></div>
        </div>

        <h2 class="font-heading text-4xl sm:text-5xl lg:text-6xl font-bold text-white leading-tight">
          {{ $t('finalCta.title') }} <br />
          <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary-400 to-amber-300">
            {{ $t('finalCta.highlight') }}
          </span>
        </h2>

        <p class="text-white/75 text-base sm:text-lg max-w-xl mx-auto leading-relaxed">
          {{ $t('finalCta.desc') }}
        </p>

        <div class="flex flex-wrap items-center justify-center gap-4 pt-4">
          <RouterLink
            to="/reservations"
            class="btn-primary text-base px-9 py-4 shadow-2xl shadow-primary-500/40 hover:scale-[1.02] active:scale-95 transition-all"
          >
            <CalendarCheck :size="18" /> {{ $t('finalCta.reserveTable') }}
          </RouterLink>

          <RouterLink
            to="/menu"
            class="inline-flex items-center gap-2 rounded-xl px-8 py-4 text-base font-semibold border-2 border-white/30 text-white hover:border-primary-400 hover:bg-white/10 transition-all"
          >
            {{ $t('finalCta.browseMenu') }} <ArrowRight :size="18" />
          </RouterLink>

          <RouterLink
            to="/rooms"
            class="inline-flex items-center gap-2 rounded-xl px-7 py-4 text-base font-semibold text-white/80 hover:text-white transition-all"
          >
            <BedDouble :size="18" class="text-primary-400" /> {{ $t('finalCta.bookRoom') }}
          </RouterLink>
        </div>
      </div>
    </section>

  </DefaultLayout>
</template>

<style scoped>
@keyframes heroZoom {
  from { transform: scale(1.04); }
  to   { transform: scale(1.12); }
}

.fade-slide-enter-active,
.fade-slide-leave-active {
  transition: opacity 0.35s ease, transform 0.35s ease;
}
.fade-slide-enter-from {
  opacity: 0;
  transform: translateX(20px);
}
.fade-slide-leave-to {
  opacity: 0;
  transform: translateX(-20px);
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.25s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
