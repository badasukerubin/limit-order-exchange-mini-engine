<template>
  <div class="p-6 space-y-8 max-w-7xl mx-auto">
    <!-- Limit Order Form -->
    <div class="bg-white shadow-lg rounded-lg p-6 md:w-1/2 w-full mx-auto">
      <h2 class="text-2xl font-bold mb-6 text-center">Place Limit Order</h2>
      <form @submit.prevent="submitOrder" class="space-y-5">
        <div class="flex flex-col md:flex-row md:space-x-4 space-y-3 md:space-y-0">
          <div class="flex-1">
            <label class="block mb-1 font-medium">Symbol</label>
            <select v-model="orderForm.symbol" class="w-full border rounded p-2 focus:ring-2 focus:ring-blue-400"
              :class="{ 'border-red-600': getError('symbol') }">
              <option value="BTC">BTC</option>
              <option value="ETH">ETH</option>
            </select>
            <p v-if="getError('symbol')" class="text-red-600 text-sm mt-1">{{ getError('symbol') }}</p>
          </div>
          <div class="flex-1">
            <label class="block mb-1 font-medium">Side</label>
            <select v-model="orderForm.side" class="w-full border rounded p-2 focus:ring-2 focus:ring-blue-400"
              :class="{ 'border-red-600': getError('side') }">
              <option value="buy">Buy</option>
              <option value="sell">Sell</option>
            </select>
            <p v-if="getError('side')" class="text-red-600 text-sm mt-1">{{ getError('side') }}</p>
          </div>
        </div>

        <div class="flex flex-col md:flex-row md:space-x-4 space-y-3 md:space-y-0">
          <div class="flex-1">
            <label class="block mb-1 font-medium">Price</label>
            <input v-model.number="orderForm.price" type="number"
              class="w-full border rounded p-2 focus:ring-2 focus:ring-blue-400"
              :class="{ 'border-red-600': getError('price') }" />
            <p v-if="getError('price')" class="text-red-600 text-sm mt-1">{{ getError('price') }}</p>
          </div>
          <div class="flex-1">
            <label class="block mb-1 font-medium">Amount</label>
            <input v-model.number="orderForm.amount" type="number"
              class="w-full border rounded p-2 focus:ring-2 focus:ring-blue-400"
              :class="{ 'border-red-600': getError('amount') }" />
            <p v-if="getError('amount')" class="text-red-600 text-sm mt-1">{{ getError('amount') }}</p>
          </div>
        </div>

        <button type="submit"
          class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg p-3 transition">Place
          Order</button>

        <p v-if="getError('general')" class="text-red-600 text-sm mt-2 text-center">{{ getError('general') }}</p>
      </form>
    </div>

    <!-- Orders & Wallet Overview -->
    <div class="bg-white shadow-lg rounded-lg p-6">
      <h2 class="text-2xl font-bold mb-4">Wallet Overview</h2>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="p-3 border rounded text-center font-medium">USD: ${{ profile.balance }}</div>
        <div v-for="(amount, symbol) in profile.assets" :key="symbol"
          class="p-3 border rounded text-center font-medium">
          {{ symbol }}: {{ amount }}
        </div>
      </div>

      <h2 class="text-2xl font-bold mb-4">Order History</h2>
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse min-w-full">
          <thead class="bg-gray-100">
            <tr>
              <th class="border-b p-2">ID</th>
              <th class="border-b p-2">Symbol</th>
              <th class="border-b p-2">Side</th>
              <th class="border-b p-2">Price</th>
              <th class="border-b p-2">Amount</th>
              <th class="border-b p-2">Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="order in orders" :key="order.id" class="border-b last:border-b-0 hover:bg-gray-50 transition">
              <td class="p-2">{{ order.id }}</td>
              <td class="p-2">{{ order.symbol }}</td>
              <td class="p-2 capitalize">{{ order.side }}</td>
              <td class="p-2">${{ order.price }}</td>
              <td class="p-2">{{ order.amount }}</td>
              <td class="p-2 capitalize">{{ order.status }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <h2 class="text-2xl font-bold mb-4 mt-6">Orderbook</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <h3 class="text-lg font-semibold mb-2 text-center">Buy Orders</h3>
          <ul class="bg-gray-50 rounded p-4 space-y-2">
            <li v-for="order in buyOrders" :key="order.id"
              class="flex justify-between py-1 px-2 border rounded hover:bg-green-50 transition">
              <span>{{ order.amount }} {{ order.symbol }}</span>
              <span>${{ order.price }}</span>
            </li>
          </ul>
        </div>
        <div>
          <h3 class="text-lg font-semibold mb-2 text-center">Sell Orders</h3>
          <ul class="bg-gray-50 rounded p-4 space-y-2">
            <li v-for="order in sellOrders" :key="order.id"
              class="flex justify-between py-1 px-2 border rounded hover:bg-red-50 transition">
              <span>{{ order.amount }} {{ order.symbol }}</span>
              <span>${{ order.price }}</span>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';
import Echo from 'laravel-echo';
import { useEcho } from '@laravel/echo-vue';
import { useAuthStore } from '@/stores/auth.js';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';

const orderForm = ref({ symbol: 'BTC', side: 'buy', price: 0, amount: 0 });
const errors = ref({});
const profile = ref({ balance: 0, assets: {}, id: null });
const orders = ref([]);
const selectedSymbol = ref('BTC');

// const toast = (message) => alert(message);

const getError = (field) => {
  const e = errors.value?.[field];
  if (!e) {
    return null;
  };

  if (Array.isArray(e)) {
    return e[0];
  };

  if (typeof e === 'string') {
    return e;
  };

  if (e && typeof e === 'object' && 'message' in e) {
    return e.message;
  };

  return String(e);
};

const fetchProfile = async () => {
  try {
    const { data } = await axios.get('/api/v1/profile');


    profile.value.balance = data.data?.balance ?? 0;

    const rawAssets = data.data?.assets;
    if (Array.isArray(rawAssets)) {
      profile.value.assets = rawAssets.reduce((acc, asset) => {
        if (asset && asset.symbol !== undefined) {
          acc[asset.symbol] = asset.amount ?? 0;
        }
        return acc;
      }, {});
    } else if (rawAssets && typeof rawAssets === 'object') {
      profile.value.assets = rawAssets;
    } else {
      profile.value.assets = {};
    }

    profile.value.id = data.data?.id ?? null;

  } catch (err) {
    console.error('Failed to fetch profile', err);
  }
};

const fetchOrders = async () => {
  try {
    const { data } = await axios.get(`/api/v1/orders?symbol=${selectedSymbol.value}`);

    // Normalize to array safely
    if (Array.isArray(data.data)) {
      orders.value = data.data;
    } else if (data.data && Array.isArray(data.data.orders)) {
      orders.value = data.data.orders;
    } else {
      orders.value = [];
    }
  } catch (err) {
    console.error('Failed to fetch orders', err);
  }
};

const submitOrder = async () => {
  errors.value = {};
  try {
    await axios.post('/api/v1/orders', orderForm.value);
    orderForm.value.price = 0;
    orderForm.value.amount = 0;

  } catch (err) {
    // handle validation (422) and non-validation responses that only include message
    if (err.response?.status === 422) {
      const resp = err.response.data;
      if (resp?.errors) {
        errors.value = resp.errors;
      } else if (resp?.message) {
        // backend returned a message instead of structured errors
        errors.value = { general: resp.message };
      } else {
        errors.value = { general: 'Validation failed' };
      }
    } else {
      // other errors: prefer server message if present
      const message = err.response?.data?.message ?? 'Unexpected error occurred';
      errors.value = { general: message };
    }
  }
};

const buyOrders = computed(() => (orders.value || []).filter(o => o && o.side === 'buy'));
const sellOrders = computed(() => (orders.value || []).filter(o => o && o.side === 'sell'));

const patchProfileAndOrders = (updates, userId) => {
  profile.value.balance = updates.balance ?? profile.value.balance;

  if (updates.assets && typeof updates.assets === 'object') {
    profile.value.assets = { ...profile.value.assets, ...updates.assets };
    profile.value.id = userId;
  }

  const newOpenOrders = updates.open_orders || [];

  orders.value = [...newOpenOrders];

  toast('Trade settled and Open Orders list updated!');
};

useEcho(`user.${useAuthStore().currentUser.id}`, '.order.matched',
  (payload) => {
    try {
      console.log('Realtime payload:', payload);

      const myUserId = useAuthStore().currentUser.id;
      const updates = payload.updates?.[myUserId];

      console.log('Order Matched Event Received. Replacing Open Orders list...');

      if (updates) {
        patchProfileAndOrders(updates, myUserId);
      }

    } catch (e) {
      console.error('Error processing OrderMatched event', e);
    }
  },
);

onMounted(() => {
  fetchProfile();
  fetchOrders();
});
</script>

<style scoped></style>
