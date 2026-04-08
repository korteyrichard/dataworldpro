import React, { useState, useEffect } from 'react';
import DashboardLayout from '../../layouts/DashboardLayout';
import { Head, usePage, router } from '@inertiajs/react';
import Pagination from '../../components/Pagination';

interface Product {
  id: number;
  name: string;
  price: number;
  size?: string;
  pivot: {
    quantity: number;
    price: number;
    beneficiary_number?: string;
  };
}

interface Order {
  id: number;
  total: number;
  status: string;
  created_at: string;
  network?: string;
  beneficiary_number?: string;
  payment_reference?: string;
  order_source?: 'shop' | 'dashboard';
  commissions?: Array<{
    id: number;
    base_price: number;
    agent_price: number;
    commission_amount: number;
    quantity: number;
  }>;
  products: Product[];
}

interface PaginatedOrders {
  data: Order[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number;
  to: number;
  links: any[];
  first_page_url: string;
  last_page_url: string;
  next_page_url: string | null;
  prev_page_url: string | null;
  path: string;
}

interface OrdersPageProps {
  orders: PaginatedOrders;
  filters?: {
    order_id?: string;
    beneficiary_number?: string;
    status?: string;
    network?: string;
  };
  auth: any;
  [key: string]: any;
}

export default function OrdersPage() {
  const { orders, filters = {}, auth } = usePage<OrdersPageProps>().props;
  const [expandedOrder, setExpandedOrder] = useState<number | null>(null);
  const [networkFilter, setNetworkFilter] = useState(filters.network || '');
  const [orderIdSearch, setOrderIdSearch] = useState(filters.order_id || '');
  const [beneficiarySearch, setBeneficiarySearch] = useState(filters.beneficiary_number || '');
  const [statusFilter, setStatusFilter] = useState(filters.status || '');

  // Extract unique networks and statuses from current page data
  const networks = Array.from(new Set(orders.data.map(o => o.network).filter(Boolean)));
  const statuses = Array.from(new Set(orders.data.map(o => o.status).filter(Boolean)));

  const handleFilterChange = () => {
    const params = {
      order_id: orderIdSearch || undefined,
      beneficiary_number: beneficiarySearch || undefined,
      status: statusFilter || undefined,
      network: networkFilter || undefined,
    };
    
    router.get(route('dashboard.orders'), params, {
      preserveState: true,
      preserveScroll: true,
    });
  };

  useEffect(() => {
    const timeoutId = setTimeout(() => {
      handleFilterChange();
    }, 300);

    return () => clearTimeout(timeoutId);
  }, [orderIdSearch, beneficiarySearch, statusFilter, networkFilter]);

  const handleExpand = (orderId: number) => {
    setExpandedOrder(expandedOrder === orderId ? null : orderId);
  };

  const getNetworkColor = (network?: string) => {
    if (!network) return '';
    if (network.toLowerCase() === 'telecel') return 'bg-red-500';
    if (network.toLowerCase() === 'mtn') return 'bg-yellow-500';
    if (network.toLowerCase() === 'bigtime' || network.toLowerCase() === 'ishare' || network.toLowerCase() === 'at data (instant)' || network.toLowerCase() === 'at (big packages)') return 'bg-blue-500';
    return '';
  };

  return (
    <DashboardLayout user={auth?.user} header={<h2 className="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">My Orders</h2>}>
      <Head title="Orders" />
      <div className="py-8 max-w-4xl mx-auto">
        <div className="mb-6 space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
              <label className="block text-sm font-medium mb-2">Search by Order ID:</label>
              <input
                type="text"
                className="border rounded px-3 py-2 w-full"
                placeholder="Enter order ID..."
                value={orderIdSearch}
                onChange={e => setOrderIdSearch(e.target.value)}
              />
            </div>
            <div>
              <label className="block text-sm font-medium mb-2">Search by Beneficiary Number:</label>
              <input
                type="text"
                className="border rounded px-3 py-2 w-full"
                placeholder="Enter beneficiary number..."
                value={beneficiarySearch}
                onChange={e => setBeneficiarySearch(e.target.value)}
              />
            </div>
            <div>
              <label className="block text-sm font-medium mb-2">Filter by Network:</label>
              <select
                className="border rounded px-3 py-2 w-full"
                value={networkFilter}
                onChange={e => setNetworkFilter(e.target.value)}
              >
                <option value="" className='text-slate-800'>All Networks</option>
                {networks.map(network => (
                  <option key={network} value={network} className='text-slate-700'>{network}</option>
                ))}
              </select>
            </div>
            <div>
              <label className="block text-sm font-medium mb-2">Filter by Status:</label>
              <select
                className="border rounded px-3 py-2 w-full"
                value={statusFilter}
                onChange={e => setStatusFilter(e.target.value)}
              >
                <option value="" className='text-slate-800'>All Statuses</option>
                {statuses.map(status => (
                  <option key={status} value={status} className='text-slate-700'>{status}</option>
                ))}
              </select>
            </div>
          </div>
        </div>
        {orders.data.length === 0 ? (
          <div>No orders found.</div>
        ) : (
          <>
            <div className="overflow-x-auto rounded-lg shadow">
              <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                <thead>
                  <tr>
                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order #</th>
                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Network</th>
                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                    <th className="px-4 py-3"></th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                  {orders.data.map(order => (
                    <React.Fragment key={order.id}>
                      <tr
                        className={`hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer transition-colors duration-200 ${getNetworkColor(order.network)}`}
                        onClick={() => handleExpand(order.id)}
                      >
                        <td className="px-4 py-3 font-bold">{order.id}</td>
                        <td className="px-4 py-3">{new Date(order.created_at).toLocaleString()}</td>
                        <td className="px-4 py-3">{order.network || '-'}</td>
                        <td className="px-4 py-3">
                          <span className={`px-2 py-1 rounded text-xs font-semibold ${order.status === 'completed' ? 'bg-green-100 text-green-700' : order.status === 'pending' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-200 text-gray-700'}`}>{order.status}</span>
                        </td>
                        <td className="px-4 py-3">GHS {order.total}</td>
                        <td className="px-4 py-3 text-right">
                          <button className="text-blue-600 hover:underline focus:outline-none" aria-label="Expand order details">
                            {expandedOrder === order.id ? 'Hide' : 'Details'}
                          </button>
                        </td>
                      </tr>
                      <tr>
                        <td colSpan={6} className={`transition-all duration-300 ease-in-out overflow-hidden p-0 ${expandedOrder === order.id ? 'h-auto' : 'h-0'}`}
                          style={{ display: expandedOrder === order.id ? 'table-cell' : 'none' }}>
                          <div className="flex flex-col gap-2 px-4 py-4">
                            <div className="font-semibold">Order Details</div>
                            <div>Status: <span className="font-semibold">{order.status}</span></div>
                            <div>Total: <span className="font-semibold">GHS {order.total}</span></div>
                            {order.payment_reference && (
                              <div>Payment Reference: <span className="font-semibold">{order.payment_reference}</span></div>
                            )}
                            {order.order_source === 'shop' && order.commissions && order.commissions.length > 0 && (
                              <div className="mt-2 p-2 bg-purple-50 rounded">
                                <div className="text-sm font-medium text-purple-700">Commission Details:</div>
                                {order.commissions.map((commission: any, index: number) => (
                                  <div key={index} className="text-sm text-purple-600 mt-1">
                                    <div>Base Price: <span className="font-semibold">GHS {commission.base_price}</span></div>
                                    <div>Agent Price: <span className="font-semibold">GHS {commission.agent_price}</span></div>
                                    <div>Commission: <span className="font-semibold">GHS {commission.commission_amount}</span></div>
                                  </div>
                                ))}
                              </div>
                            )}
                            <div>Products:</div>
                            <ul className="ml-4 list-disc space-y-2">
                              {order.products.map(product => (
                                <li key={product.id} className="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1">
                                  <span>{product.name} {product.size ? `(${product.size})` : ''} - GHS {product.pivot.price}</span>
                                  <span className="text-sm text-gray-600 dark:text-gray-400">
                                    Beneficiary: {product.pivot.beneficiary_number || '-'}
                                  </span>
                                </li>
                              ))}
                            </ul>
                          </div>
                        </td>
                      </tr>
                    </React.Fragment>
                  ))}
                </tbody>
              </table>
            </div>
            <Pagination data={orders} />
          </>
        )}
      </div>
    </DashboardLayout>
  );
}