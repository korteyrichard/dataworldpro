import React from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import { AdminLayout } from '@/layouts/admin-layout';
import { PageProps, User } from '@/types';

interface Product {
  id: number;
  name: string;
  network: string;
  amount: number;
}

interface Order {
  id: number;
  user: User;
  total_amount: number;
  status: string;
}

interface Transaction {
  id: number;
  user: User;
  amount: number;
  type: string;
}

interface AdminDashboardProps extends PageProps {
  stats: {
    totalUsers: number;
    totalProducts: number;
    totalOrders: number;
    totalTransactions: number;
    todayUsers: number;
    todayOrders: number;
    todayTransactions: number;
  };
  jaybartOrderPusherEnabled: boolean;
  codecraftOrderPusherEnabled: boolean;
  jescoOrderPusherEnabled: boolean;
  easydataOrderPusherEnabled: boolean;
}

const StatCard = ({ title, value }: { title: string; value: number | string }) => (
  <div className="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-md hover:shadow-lg transition-shadow">
    <h3 className="text-sm font-medium text-gray-500 dark:text-gray-300">{title}</h3>
    <p className="text-3xl font-bold text-gray-900 dark:text-white mt-2">{value}</p>
  </div>
);

const AdminDashboard: React.FC<AdminDashboardProps> = ({
  stats,
  jaybartOrderPusherEnabled,
  codecraftOrderPusherEnabled,
  jescoOrderPusherEnabled,
  easydataOrderPusherEnabled,
}) => {
  const { auth } = usePage<AdminDashboardProps>().props;

  const toggleJaybartOrderPusher = () => {
    router.post('/admin/toggle-jaybart-order-pusher', {
      enabled: !jaybartOrderPusherEnabled
    });
  };

  const toggleCodecraftOrderPusher = () => {
    router.post('/admin/toggle-codecraft-order-pusher', {
      enabled: !codecraftOrderPusherEnabled
    });
  };

  const toggleJescoOrderPusher = () => {
    router.post('/admin/toggle-jesco-order-pusher', {
      enabled: !jescoOrderPusherEnabled
    });
  };

  const toggleEasydataOrderPusher = () => {
    router.post('/admin/toggle-easydata-order-pusher', {
      enabled: !easydataOrderPusherEnabled
    });
  };

  return (
    <AdminLayout
      user={auth?.user}
      header={<h2 className="text-3xl font-bold text-gray-800 dark:text-white">Admin Dashboard</h2>}
    >
      <Head title="Admin Dashboard" />

      <div className="p-6 space-y-10">
        {/* Summary Section */}
        <section>
          <h3 className="text-xl font-semibold text-gray-700 dark:text-gray-200 mb-4">Overall Summary</h3>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <StatCard title="Total Users" value={stats.totalUsers} />
            <StatCard title="Total Products" value={stats.totalProducts} />
            <StatCard title="Total Orders" value={stats.totalOrders} />
            <StatCard title="Total Transactions" value={stats.totalTransactions} />
          </div>
        </section>

        {/* Today Section */}
        <section>
          <h3 className="text-xl font-semibold text-gray-700 dark:text-gray-200 mb-4">Today's Statistics</h3>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <StatCard title="New Users Today" value={stats.todayUsers} />
            <StatCard title="Orders Today" value={stats.todayOrders} />
            <StatCard title="Transactions Today" value={stats.todayTransactions} />
          </div>
        </section>

        {/* Order Pusher Controls */}
        <section>
          <h3 className="text-xl font-semibold text-gray-700 dark:text-gray-200 mb-4">System Controls</h3>
          <div className="space-y-4">
            {/* Jaybart Order Pusher */}
            <div className="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-md">
              <div className="flex items-center justify-between">
                <div>
                  <h4 className="text-lg font-medium text-gray-900 dark:text-white">Jaybart Order Pusher</h4>
                  <p className="text-sm text-gray-500 dark:text-gray-300">
                    {jaybartOrderPusherEnabled ? 'Orders are being pushed to Jaybart API' : 'Jaybart order pushing is disabled'}
                  </p>
                </div>
                <button
                  onClick={toggleJaybartOrderPusher}
                  className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 ${
                    jaybartOrderPusherEnabled ? 'bg-indigo-600' : 'bg-gray-200 dark:bg-gray-600'
                  }`}
                >
                  <span
                    className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                      jaybartOrderPusherEnabled ? 'translate-x-6' : 'translate-x-1'
                    }`}
                  />
                </button>
              </div>
            </div>

            {/* CodeCraft Order Pusher */}
            <div className="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-md">
              <div className="flex items-center justify-between">
                <div>
                  <h4 className="text-lg font-medium text-gray-900 dark:text-white">CodeCraft Order Pusher</h4>
                  <p className="text-sm text-gray-500 dark:text-gray-300">
                    {codecraftOrderPusherEnabled ? 'Orders are being pushed to CodeCraft API' : 'CodeCraft order pushing is disabled'}
                  </p>
                </div>
                <button
                  onClick={toggleCodecraftOrderPusher}
                  className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 ${
                    codecraftOrderPusherEnabled ? 'bg-indigo-600' : 'bg-gray-200 dark:bg-gray-600'
                  }`}
                >
                  <span
                    className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                      codecraftOrderPusherEnabled ? 'translate-x-6' : 'translate-x-1'
                    }`}
                  />
                </button>
              </div>
            </div>

            {/* Jesco Order Pusher */}
            <div className="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-md">
              <div className="flex items-center justify-between">
                <div>
                  <h4 className="text-lg font-medium text-gray-900 dark:text-white">Jesco Order Pusher</h4>
                  <p className="text-sm text-gray-500 dark:text-gray-300">
                    {jescoOrderPusherEnabled ? 'MTN orders are being pushed to Jesco API' : 'Jesco order pushing is disabled'}
                  </p>
                </div>
                <button
                  onClick={toggleJescoOrderPusher}
                  className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 ${
                    jescoOrderPusherEnabled ? 'bg-indigo-600' : 'bg-gray-200 dark:bg-gray-600'
                  }`}
                >
                  <span
                    className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                      jescoOrderPusherEnabled ? 'translate-x-6' : 'translate-x-1'
                    }`}
                  />
                </button>
              </div>
            </div>

            {/* EasyData Order Pusher */}
            <div className="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-md">
              <div className="flex items-center justify-between">
                <div>
                  <h4 className="text-lg font-medium text-gray-900 dark:text-white">EasyData Order Pusher</h4>
                  <p className="text-sm text-gray-500 dark:text-gray-300">
                    {easydataOrderPusherEnabled ? 'MTN orders are being pushed to EasyData API' : 'EasyData order pushing is disabled'}
                  </p>
                </div>
                <button
                  onClick={toggleEasydataOrderPusher}
                  className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 ${
                    easydataOrderPusherEnabled ? 'bg-indigo-600' : 'bg-gray-200 dark:bg-gray-600'
                  }`}
                >
                  <span
                    className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                      easydataOrderPusherEnabled ? 'translate-x-6' : 'translate-x-1'
                    }`}
                  />
                </button>
              </div>
            </div>
          </div>
        </section>
      </div>
    </AdminLayout>
  );
};

export default AdminDashboard;
