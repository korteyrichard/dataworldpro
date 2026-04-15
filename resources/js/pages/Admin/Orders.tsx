import React, { useState } from 'react';
import { AdminLayout } from '../../layouts/admin-layout';
import { Head, usePage, router } from '@inertiajs/react';
import Pagination from '@/components/pagination';

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
  order_pusher_status: 'disabled' | 'success' | 'failed' | null | undefined;
  order_source?: 'shop' | 'dashboard' | 'api';
  commissions?: Array<{
    id: number;
    base_price: number;
    agent_price: number;
    commission_amount: number;
    quantity: number;
  }>;
  products: Product[];
  user: {
    id: number;
    name: string;
    email: string;
    shop?: {
      name: string;
      owner_name: string;
    };
  };
}

interface PaginatedOrders {
  data: Order[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number;
  to: number;
  links: Array<{
    url: string | null;
    label: string;
    active: boolean;
  }>;
}

interface AdminOrdersPageProps {
  orders: PaginatedOrders;
  auth: any;
  filterNetwork: string;
  filterStatus: string;
  filterOrderPusherStatus: string;
  filterOrderSource: string;
  searchOrderId: string;
  searchBeneficiaryNumber: string;
  dailyTotalSales: number;
  [key: string]: any;
}

export default function AdminOrders() {
  const {
    orders,
    auth,
    filterNetwork: initialNetworkFilter,
    filterStatus: initialStatusFilter,
    filterOrderPusherStatus: initialOrderPusherStatusFilter,
    filterOrderSource: initialOrderSourceFilter,
    searchOrderId: initialSearchOrderId,
    searchBeneficiaryNumber: initialSearchBeneficiaryNumber,
    dailyTotalSales,
  } = usePage<AdminOrdersPageProps>().props;

  const [expandedOrder, setExpandedOrder] = useState<number | null>(null);
  const [networkFilter, setNetworkFilter] = useState(initialNetworkFilter || '');
  const [statusFilter, setStatusFilter] = useState(initialStatusFilter || '');
  const [orderPusherStatusFilter, setOrderPusherStatusFilter] = useState(initialOrderPusherStatusFilter || '');
  const [searchOrderId, setSearchOrderId] = useState(initialSearchOrderId || '');
  const [searchBeneficiaryNumber, setSearchBeneficiaryNumber] = useState(initialSearchBeneficiaryNumber || '');
  const [orderSourceFilter, setOrderSourceFilter] = useState(initialOrderSourceFilter || '');
  const [selectedOrders, setSelectedOrders] = useState<number[]>([]);
  const [bulkStatus, setBulkStatus] = useState('');

  const networks = Array.from(new Set(orders.data.map(o => o.network).filter(Boolean)));

  const handleFilterChange = (filterName: string, value: string) => {
    const params: any = {};
    
    if (filterName === 'network') {
      setNetworkFilter(value);
      if (value) params.network = value;
    } else if (networkFilter) {
      params.network = networkFilter;
    }
    
    if (filterName === 'status') {
      setStatusFilter(value);
      if (value) params.status = value;
    } else if (statusFilter) {
      params.status = statusFilter;
    }
    
    if (filterName === 'order_pusher_status') {
      setOrderPusherStatusFilter(value);
      if (value) params.order_pusher_status = value;
    } else if (orderPusherStatusFilter) {
      params.order_pusher_status = orderPusherStatusFilter;
    }
    
    if (filterName === 'order_source') {
      setOrderSourceFilter(value);
      if (value) params.order_source = value;
    } else if (orderSourceFilter) {
      params.order_source = orderSourceFilter;
    }
    
    router.get(route('admin.orders'), params, { preserveState: true, replace: true });
  };

  const handleSearch = (searchType: 'order_id' | 'beneficiary_number', value: string) => {
    const searchParams: any = {};
    if (searchType === 'order_id' && value) {
      searchParams.order_id = value;
    } else if (searchType === 'beneficiary_number' && value) {
      searchParams.beneficiary_number = value;
    }
    
    if (searchType === 'order_id') {
      setSearchOrderId(value);
    } else {
      setSearchBeneficiaryNumber(value);
    }
    
    router.get(route('admin.orders'), searchParams, { preserveState: true, replace: true });
  };

  const handleExpand = (orderId: number) => {
    setExpandedOrder(expandedOrder === orderId ? null : orderId);
  };

  const getNetworkColor = (network?: string) => {
    if (!network) return 'bg-gray-200 text-gray-700';
    const map: Record<string, string> = {
      telecel: 'bg-red-100 text-red-700',
      mtn: 'bg-yellow-100 text-yellow-800',
      bigtime: 'bg-blue-100 text-blue-700',
      ishare: 'bg-blue-100 text-blue-700',
      'at data (instant)': 'bg-blue-100 text-blue-700',
      'at (big packages)': 'bg-blue-100 text-blue-700',
    };
    return map[network.toLowerCase()] || 'bg-gray-200 text-gray-700';
  };

  const getOrderPusherStatusColor = (status: 'disabled' | 'success' | 'failed' | null | undefined) => {
    const map: Record<string, string> = {
      disabled: 'bg-gray-100 text-gray-700',
      success: 'bg-green-100 text-green-700',
      failed: 'bg-red-100 text-red-700',
    };
    return map[status || 'disabled'];
  };

  const handleDeleteOrder = (orderId: number) => {
    if (confirm('Are you sure you want to delete this order?')) {
      router.delete(route('admin.orders.delete', orderId), {
        onSuccess: () => router.reload(),
        onError: () => alert('Failed to delete order.'),
      });
    }
  };

  const handleStatusChange = (orderId: number, newStatus: string) => {
    router.put(route('admin.orders.updateStatus', orderId), { status: newStatus }, {
      onSuccess: () => router.reload(),
      onError: () => alert('Failed to update order status.'),
    });
  };

  const handleSelectOrder = (orderId: number) => {
    setSelectedOrders(prev => 
      prev.includes(orderId) 
        ? prev.filter(id => id !== orderId)
        : [...prev, orderId]
    );
  };

  const handleSelectAll = () => {
    setSelectedOrders(selectedOrders.length === orders.data.length ? [] : orders.data.map(o => o.id));
  };

  const handleBulkStatusUpdate = () => {
    if (selectedOrders.length === 0 || !bulkStatus) return;
    
    router.put(route('admin.orders.bulkUpdateStatus'), {
      order_ids: selectedOrders,
      status: bulkStatus
    }, {
      onSuccess: () => {
        setSelectedOrders([]);
        setBulkStatus('');
        router.reload();
      },
      onError: () => alert('Failed to update order statuses.'),
    });
  };

  return (
    <AdminLayout
      user={auth?.user}
      header={<h2 className="text-3xl font-bold text-gray-800 dark:text-white">Orders</h2>}
    >
      <Head title="Admin Orders" />
      <div className="max-w-6xl mx-auto py-10 px-2 sm:px-4">
        {/* Daily Total Sales */}
        <div className="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl p-4 mb-6">
          <div className="text-center">
            <h3 className="text-lg font-semibold text-green-700 dark:text-green-300">Daily Total Sales</h3>
            <p className="text-2xl font-bold text-green-800 dark:text-green-200">GHS {dailyTotalSales}</p>
          </div>
        </div>
        {/* Bulk Actions */}
        {selectedOrders.length > 0 && (
          <div className="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-4 mb-6">
            <div className="flex flex-col sm:flex-row sm:items-center gap-3">
              <span className="text-sm font-medium text-blue-700 dark:text-blue-300">
                {selectedOrders.length} order(s) selected
              </span>
              <div className="flex gap-2">
                <select
                  className="px-3 py-1.5 rounded-lg border border-blue-300 dark:border-blue-600 bg-white dark:bg-gray-800 text-sm"
                  value={bulkStatus}
                  onChange={(e) => setBulkStatus(e.target.value)}
                >
                  <option value="">Change status to...</option>
                  <option value="pending">Pending</option>
                  <option value="processing">Processing</option>
                  <option value="completed">Completed</option>
                  <option value="cancelled">Cancelled</option>
                </select>
                <button
                  onClick={handleBulkStatusUpdate}
                  disabled={!bulkStatus}
                  className="px-4 py-1.5 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  Update
                </button>
                <button
                  onClick={() => {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = route('admin.orders.export');
                    form.style.display = 'none';
                    
                    const csrfInput = document.createElement('input');
                    csrfInput.type = 'hidden';
                    csrfInput.name = '_token';
                    csrfInput.value = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                    form.appendChild(csrfInput);
                    
                    selectedOrders.forEach(orderId => {
                      const input = document.createElement('input');
                      input.type = 'hidden';
                      input.name = 'order_ids[]';
                      input.value = orderId.toString();
                      form.appendChild(input);
                    });
                    
                    document.body.appendChild(form);
                    form.submit();
                    document.body.removeChild(form);
                  }}
                  className="px-4 py-1.5 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700"
                >
                  Export CSV
                </button>
              </div>
            </div>
          </div>
        )}

        {/* Filters */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4 mb-8">
          <div className="flex flex-col gap-1">
            <label className="text-sm font-medium text-gray-700 dark:text-gray-300">Filter by Network</label>
            <select
              className="px-3 py-2 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 dark:text-white shadow-sm focus:ring focus:ring-blue-500 text-sm"
              value={networkFilter}
              onChange={(e) => handleFilterChange('network', e.target.value)}
            >
              <option value="">--select network--</option>
              {networks.map(network => (
                <option key={network} value={network}>{network}</option>
              ))}
            </select>
          </div>

          <div className="flex flex-col gap-1">
            <label className="text-sm font-medium text-gray-700 dark:text-gray-300">Filter by Status</label>
            <select
              className="px-3 py-2 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 dark:text-white shadow-sm focus:ring focus:ring-blue-500 text-sm"
              value={statusFilter}
              onChange={(e) => handleFilterChange('status', e.target.value)}
            >
              <option value="">--select status--</option>
              <option value="pending">Pending</option>
              <option value="processing">Processing</option>
              <option value="completed">Completed</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>

          <div className="flex flex-col gap-1">
            <label className="text-sm font-medium text-gray-700 dark:text-gray-300">Filter by API Status</label>
            <select
              className="px-3 py-2 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 dark:text-white shadow-sm focus:ring focus:ring-blue-500 text-sm"
              value={orderPusherStatusFilter}
              onChange={(e) => handleFilterChange('order_pusher_status', e.target.value)}
            >
              <option value="">--select API status--</option>
              <option value="success">Success</option>
              <option value="failed">Failed</option>
            </select>
          </div>

          <div className="flex flex-col gap-1">
            <label className="text-sm font-medium text-gray-700 dark:text-gray-300">Filter by Order Source</label>
            <select
              className="px-3 py-2 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 dark:text-white shadow-sm focus:ring focus:ring-blue-500 text-sm"
              value={orderSourceFilter}
              onChange={(e) => handleFilterChange('order_source', e.target.value)}
            >
              <option value="">--select source--</option>
              <option value="dashboard">Dashboard</option>
              <option value="shop">Shop</option>
              <option value="api">API</option>
            </select>
          </div>

          <div className="flex flex-col gap-1">
            <label className="text-sm font-medium text-gray-700 dark:text-gray-300">Search by Order ID</label>
            <input
              type="text"
              placeholder="Enter order ID"
              className="px-3 py-2 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 dark:text-white shadow-sm focus:ring focus:ring-blue-500 text-sm"
              value={searchOrderId}
              onChange={(e) => handleSearch('order_id', e.target.value)}
            />
          </div>

          <div className="flex flex-col gap-1">
            <label className="text-sm font-medium text-gray-700 dark:text-gray-300">Search by Beneficiary Number</label>
            <input
              type="text"
              placeholder="Enter phone number"
              className="px-3 py-2 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 dark:text-white shadow-sm focus:ring focus:ring-blue-500 text-sm"
              value={searchBeneficiaryNumber}
              onChange={(e) => handleSearch('beneficiary_number', e.target.value)}
            />
          </div>
        </div>

        {/* Orders Table */}
        {orders.data.length === 0 ? (
          <div className="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 p-6 rounded-xl text-center shadow-md">
            No orders found for the selected filters.
          </div>
        ) : (
          <div className="overflow-x-auto rounded-xl shadow-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
            <table className="min-w-[600px] w-full text-sm text-left text-gray-700 dark:text-gray-300">
              <thead className="uppercase text-xs bg-gray-100 dark:bg-gray-700 dark:text-gray-300">
                <tr>
                  <th className="px-3 sm:px-5 py-3 sm:py-4 w-12">
                    <input
                      type="checkbox"
                      checked={selectedOrders.length === orders.data.length && orders.data.length > 0}
                      onChange={handleSelectAll}
                      className="rounded border-gray-300 dark:border-gray-600"
                    />
                  </th>
                  <th className="px-3 sm:px-5 py-3 sm:py-4">Order #</th>
                  <th className="px-3 sm:px-5 py-3 sm:py-4">User</th>
                  <th className="px-3 sm:px-5 py-3 sm:py-4">Date</th>
                  <th className="px-3 sm:px-5 py-3 sm:py-4">Network</th>
                  <th className="px-3 sm:px-5 py-3 sm:py-4">Status</th>
                  <th className="px-3 sm:px-5 py-3 sm:py-4">API Status</th>
                  <th className="px-3 sm:px-5 py-3 sm:py-4">Total</th>
                  <th className="px-3 sm:px-5 py-3 sm:py-4 text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                {orders.data.map((order) => (
                  <React.Fragment key={order.id}>
                    <tr className="hover:bg-gray-50 dark:hover:bg-gray-800 border-t border-gray-200 dark:border-gray-700 transition">
                      <td className="px-3 sm:px-5 py-3 sm:py-4">
                        <input
                          type="checkbox"
                          checked={selectedOrders.includes(order.id)}
                          onChange={() => handleSelectOrder(order.id)}
                          className="rounded border-gray-300 dark:border-gray-600"
                        />
                      </td>
                      <td className="px-3 sm:px-5 py-3 sm:py-4 font-semibold">{order.id}</td>
                      <td className="px-3 sm:px-5 py-3 sm:py-4">
                        <div className="text-sm">
                          <div className="flex items-center gap-2">
                            <span className="font-medium">
                              {order.order_source === 'shop' ? 
                                (order.buyer_email ? order.buyer_email.split('@')[0] : 'Guest Customer') : 
                                (order.user?.name || 'Unknown User')
                              }
                            </span>
                            {order.order_source === 'shop' && (
                              <span className="px-2 py-1 bg-purple-100 text-purple-700 text-xs rounded-full font-medium">
                                Shop Order
                              </span>
                            )}
                            {order.order_source === 'api' && (
                              <span className="px-2 py-1 bg-blue-100 text-blue-700 text-xs rounded-full font-medium">
                                API Order
                              </span>
                            )}
                            {order.order_source === 'dashboard' && (
                              <span className="px-2 py-1 bg-green-100 text-green-700 text-xs rounded-full font-medium">
                                Dashboard Order
                              </span>
                            )}
                            {!order.order_source && (
                              <span className="px-2 py-1 bg-gray-100 text-gray-700 text-xs rounded-full font-medium">
                                Legacy Order
                              </span>
                            )}
                          </div>
                          <div className="text-gray-500 text-xs">
                            {order.order_source === 'shop' ? 
                              (order.buyer_email || 'No email') : 
                              (order.user?.email || 'No email')
                            }
                          </div>
                        </div>
                      </td>
                      <td className="px-3 sm:px-5 py-3 sm:py-4 whitespace-nowrap">{new Date(order.created_at).toLocaleString()}</td>
                      <td className={`px-3 sm:px-5 py-3 sm:py-4 rounded ${getNetworkColor(order.network)} font-medium`}>
                        {order.network || '-'}
                      </td>
                      <td className="px-3 sm:px-5 py-3 sm:py-4">
                        <select
                          className="px-2 py-1 rounded-md text-xs dark:bg-gray-800 bg-gray-100"
                          value={order.status}
                          onChange={(e) => handleStatusChange(order.id, e.target.value)}
                          onClick={(e) => e.stopPropagation()}
                        >
                            <option value="pending">Pending</option>
                            <option value="processing">Processing</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                      </td>
                      <td className={`px-3 sm:px-5 py-3 sm:py-4 rounded ${getOrderPusherStatusColor(order.order_pusher_status || 'disabled')} font-medium text-xs`}>
                        {order.order_pusher_status ? order.order_pusher_status.charAt(0).toUpperCase() + order.order_pusher_status.slice(1) : 'Disabled'}
                      </td>
                      <td className="px-3 sm:px-5 py-3 sm:py-4 font-semibold">GHS {order.total}</td>
                      <td className="px-3 sm:px-5 py-3 sm:py-4 text-right space-x-2 sm:space-x-3">
                        <button
                          onClick={() => handleExpand(order.id)}
                          className="text-blue-600 dark:text-blue-400 hover:underline text-xs sm:text-sm"
                        >
                          {expandedOrder === order.id ? 'Hide' : 'Details'}
                        </button>
                        <button
                          onClick={() => handleDeleteOrder(order.id)}
                          className="text-red-500 hover:underline text-xs sm:text-sm"
                        >
                          Delete
                        </button>
                      </td>
                    </tr>

                    {expandedOrder === order.id && (
                      <tr className="bg-gray-50 dark:bg-gray-800 border-b dark:border-gray-700">
                        <td colSpan={9} className="px-3 sm:px-6 py-4 sm:py-5">
                          <div className="space-y-2 text-xs sm:text-sm">
                            <p><strong>Status:</strong> {order.status}</p>
                            <p><strong>API Status:</strong> <span className={`px-2 py-1 rounded text-xs ${getOrderPusherStatusColor(order.order_pusher_status || 'disabled')}`}>{order.order_pusher_status ? order.order_pusher_status.charAt(0).toUpperCase() + order.order_pusher_status.slice(1) : 'Disabled'}</span></p>
                            {order.payment_reference && (
                              <p><strong>Payment Reference:</strong> {order.payment_reference}</p>
                            )}
                            {order.order_source === 'shop' && order.commissions && order.commissions.length > 0 && (
                              <div className="mt-2 p-2 bg-purple-50 dark:bg-purple-900/20 rounded">
                                <p className="font-medium text-purple-700 dark:text-purple-300">Commission Details:</p>
                                {order.commissions.map((commission: any, index: number) => (
                                  <div key={index} className="text-xs text-purple-600 dark:text-purple-400 mt-1">
                                    <p>Base Price: <span className="font-semibold">GHS {commission.base_price}</span></p>
                                    <p>Agent Price: <span className="font-semibold">GHS {commission.agent_price}</span></p>
                                    <p>Commission: <span className="font-semibold">GHS {commission.commission_amount}</span></p>
                                  </div>
                                ))}
                              </div>
                            )}
                            <p><strong>Products:</strong></p>
                            <ul className="list-disc pl-4 sm:pl-5 space-y-1">
                              {order.products?.filter(product => product).map((product) => (
                                <li key={product.id} className="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1 sm:gap-0">
                                  <span>
                                    {product?.name || 'Unknown Product'}{product?.size ? ` (${product.size})` : ''} - GHS {product?.pivot?.price || 0}
                                  </span>
                                  <span className="text-xs text-gray-600 dark:text-gray-400">
                                    Beneficiary: {product?.pivot?.beneficiary_number || '-'}
                                  </span>
                                </li>
                              )) || []}
                            </ul>
                          </div>
                        </td>
                      </tr>
                    )}
                  </React.Fragment>
                ))}
              </tbody>
            </table>
          </div>
        )}
        
        {/* Pagination */}
        <Pagination data={orders} />
      </div>
    </AdminLayout>
  );
}
