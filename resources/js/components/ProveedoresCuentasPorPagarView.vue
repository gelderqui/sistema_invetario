<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div><h2 class="h4 mb-0">Cuentas por pagar a proveedores</h2><small class="text-body-secondary">Deudas por compras a crédito y mercancía consignada ya vendida.</small></div>
      <button class="btn btn-brand" :disabled="!selectedRows.length || saving" @click="openPayment">Registrar pago</button>
    </div>
    <div v-if="loading" class="text-center py-5 text-body-secondary">Cargando información...</div>
    <div v-else class="card border-0 shadow-sm">
      <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead class="thead-brand"><tr><th></th><th>Proveedor</th><th>Origen</th><th>Referencia</th><th class="text-end">Cantidad</th><th class="text-end">Costo</th><th class="text-end">Saldo</th><th>Estado</th></tr></thead>
        <tbody>
          <tr v-if="!cuentas.length"><td colspan="8" class="text-center text-body-secondary py-4">No hay cuentas pendientes.</td></tr>
          <tr v-for="cuenta in cuentas" :key="cuenta.id">
            <td><input v-model="selectedIds" class="form-check-input" type="checkbox" :value="cuenta.id"></td>
            <td>{{ cuenta.proveedor?.nombre }}</td><td><span :class="['badge', cuenta.origen === 'compra_credito' ? 'text-bg-warning' : 'text-bg-info']">{{ originLabel(cuenta) }}</span></td><td>{{ accountReference(cuenta) }}</td>
            <td class="text-end">{{ cuenta.origen === 'compra_credito' ? '—' : Number(cuenta.cantidad).toFixed(0) }}</td><td class="text-end">{{ cuenta.origen === 'compra_credito' ? '—' : `Q ${money(cuenta.costo_unitario)}` }}</td><td class="text-end fw-semibold">Q {{ money(cuenta.saldo_pendiente) }}</td>
            <td><span class="badge text-bg-warning text-uppercase">{{ cuenta.estado }}</span></td>
          </tr>
        </tbody>
      </table></div>
    </div>
    <div ref="modalRef" class="modal fade" tabindex="-1"><div class="modal-dialog"><form class="modal-content" @submit.prevent="pay">
      <div class="modal-header modal-header-brand"><h5 class="modal-title">Registrar pago</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body d-grid gap-3">
        <div v-if="error" class="alert alert-danger mb-0">{{ error }}</div>
        <div><label class="form-label">Proveedor</label><input class="form-control" :value="selectedProvider?.nombre || ''" readonly></div>
        <div><label class="form-label">Fecha</label><input v-model="payment.fecha" type="date" class="form-control" required></div>
        <div><label class="form-label">Pagar desde</label><select v-model="payment.metodo_pago" class="form-select"><option v-for="m in catalogs.metodos_pago" :key="m.value" :value="m.value">{{ m.label }}</option></select></div>
        <div v-for="cuenta in selectedRows" :key="cuenta.id" class="border rounded p-2"><div class="small">{{ originLabel(cuenta) }} · {{ accountReference(cuenta) }} · saldo Q {{ money(cuenta.saldo_pendiente) }}</div><input v-model.number="amounts[cuenta.id]" type="number" min="0.01" :max="Number(cuenta.saldo_pendiente)" step="0.01" class="form-control mt-1"></div>
        <div class="fw-semibold text-end">Total: Q {{ money(paymentTotal) }}</div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-brand" :disabled="saving">{{ saving ? 'Guardando...' : 'Guardar pago' }}</button></div>
    </form></div></div>
  </div>
</template>
<script setup>
import { Modal } from 'bootstrap';
import { computed, onMounted, ref } from 'vue';
import axios from '@/bootstrap';
import { formatMoney } from '@/utils/formatters';
const cuentas=ref([]), catalogs=ref({metodos_pago:[]}), selectedIds=ref([]), amounts=ref({}), loading=ref(true), saving=ref(false), error=ref(''), modalRef=ref(null); let modal;
const selectedRows=computed(()=>cuentas.value.filter(c=>selectedIds.value.includes(c.id)));
const selectedProvider=computed(()=>selectedRows.value[0]?.proveedor);
const payment=ref({fecha:new Date().toISOString().slice(0,10),metodo_pago:'caja_general'});
const paymentTotal=computed(()=>selectedRows.value.reduce((total,c)=>total+Number(amounts.value[c.id]||0),0));
const money=v=>formatMoney(v);
const originLabel=cuenta=>(cuenta?.origen === 'compra_credito' ? 'Compra a crédito' : 'Consignación vendida');
const accountReference=cuenta=>(cuenta?.origen === 'compra_credito' ? (cuenta?.compra?.numero ?? '-') : `${cuenta?.venta?.numero ?? '-'} · ${cuenta?.compra?.numero ?? '-'}`);
onMounted(async()=>{ modal=new Modal(modalRef.value); await Promise.all([load(),loadCatalogs()]); });
async function load(){loading.value=true;try{const {data}=await axios.get('/cuentas-por-pagar/get');cuentas.value=data.data;}finally{loading.value=false;}}
async function loadCatalogs(){const {data}=await axios.get('/cuentas-por-pagar/get/catalogs');catalogs.value=data.data;}
function openPayment(){const provider=selectedRows.value[0]?.proveedor_id;if(selectedRows.value.some(c=>c.proveedor_id!==provider)){error.value='Seleccione cargos de un solo proveedor.';return;} error.value=''; amounts.value=Object.fromEntries(selectedRows.value.map(c=>[c.id,Number(c.saldo_pendiente)])); modal.show();}
async function pay(){if(paymentTotal.value<=0){error.value='Ingrese un monto válido.';return;} saving.value=true;error.value='';try{await axios.post('/cuentas-por-pagar/pagos',{proveedor_id:selectedRows.value[0].proveedor_id,...payment.value,aplicaciones:selectedRows.value.map(c=>({cuenta_id:c.id,monto:Number(amounts.value[c.id]||0)})).filter(a=>a.monto>0)});selectedIds.value=[];modal.hide();await load();}catch(e){error.value=e.response?.data?.message||Object.values(e.response?.data?.errors||{}).flat()[0]||'No se pudo registrar el pago.';}finally{saving.value=false;}}
</script>
