import { Link } from '@inertiajs/react';

interface PaginationLink {
  url: string | null;
  label: string;
  active: boolean;
}

interface PaginationData {
  current_page: number;
  data: any[];
  first_page_url: string;
  from: number;
  last_page: number;
  last_page_url: string;
  links: PaginationLink[];
  next_page_url: string | null;
  path: string;
  per_page: number;
  prev_page_url: string | null;
  to: number;
  total: number;
}

interface PaginationProps {
  data: PaginationData;
  preserveScroll?: boolean;
}

export default function Pagination({ data, preserveScroll = true }: PaginationProps) {
  if (data.last_page <= 1) return null;

  // Get current URL search params to preserve filters
  const currentParams = new URLSearchParams(window.location.search);
  
  const addParamsToUrl = (url: string | null) => {
    if (!url) return null;
    const urlObj = new URL(url, window.location.origin);
    currentParams.forEach((value, key) => {
      if (key !== 'page') { // Don't duplicate page param
        urlObj.searchParams.set(key, value);
      }
    });
    return urlObj.toString();
  };

  return (
    <div className="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6 dark:bg-gray-800 dark:border-gray-700">
      <div className="flex flex-1 justify-between sm:hidden">
        {data.prev_page_url ? (
          <Link
            href={addParamsToUrl(data.prev_page_url) || '#'}
            preserveScroll={preserveScroll}
            className="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
          >
            Previous
          </Link>
        ) : (
          <span className="relative inline-flex items-center rounded-md border border-gray-300 bg-gray-100 px-4 py-2 text-sm font-medium text-gray-400 dark:bg-gray-700 dark:border-gray-600">
            Previous
          </span>
        )}
        {data.next_page_url ? (
          <Link
            href={addParamsToUrl(data.next_page_url) || '#'}
            preserveScroll={preserveScroll}
            className="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
          >
            Next
          </Link>
        ) : (
          <span className="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-gray-100 px-4 py-2 text-sm font-medium text-gray-400 dark:bg-gray-700 dark:border-gray-600">
            Next
          </span>
        )}
      </div>
      <div className="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
        <div>
          <p className="text-sm text-gray-700 dark:text-gray-300">
            Showing <span className="font-medium">{data.from}</span> to{' '}
            <span className="font-medium">{data.to}</span> of{' '}
            <span className="font-medium">{data.total}</span> results
          </p>
        </div>
        <div>
          <nav className="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
            {data.links.map((link, index) => {
              if (link.label === '&laquo; Previous') {
                return link.url ? (
                  <Link
                    key={index}
                    href={addParamsToUrl(link.url) || '#'}
                    preserveScroll={preserveScroll}
                    className="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0 dark:ring-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                  >
                    <span className="sr-only">Previous</span>
                    <svg className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                      <path fillRule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clipRule="evenodd" />
                    </svg>
                  </Link>
                ) : (
                  <span
                    key={index}
                    className="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-300 ring-1 ring-inset ring-gray-300 dark:ring-gray-600 dark:text-gray-500"
                  >
                    <span className="sr-only">Previous</span>
                    <svg className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                      <path fillRule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clipRule="evenodd" />
                    </svg>
                  </span>
                );
              }
              
              if (link.label === 'Next &raquo;') {
                return link.url ? (
                  <Link
                    key={index}
                    href={addParamsToUrl(link.url) || '#'}
                    preserveScroll={preserveScroll}
                    className="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0 dark:ring-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                  >
                    <span className="sr-only">Next</span>
                    <svg className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                      <path fillRule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clipRule="evenodd" />
                    </svg>
                  </Link>
                ) : (
                  <span
                    key={index}
                    className="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-300 ring-1 ring-inset ring-gray-300 dark:ring-gray-600 dark:text-gray-500"
                  >
                    <span className="sr-only">Next</span>
                    <svg className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                      <path fillRule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clipRule="evenodd" />
                    </svg>
                  </span>
                );
              }

              return link.url ? (
                <Link
                  key={index}
                  href={addParamsToUrl(link.url) || '#'}
                  preserveScroll={preserveScroll}
                  className={`relative inline-flex items-center px-4 py-2 text-sm font-semibold ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0 dark:ring-gray-600 dark:hover:bg-gray-700 ${
                    link.active
                      ? 'z-10 bg-indigo-600 text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600'
                      : 'text-gray-900 dark:text-gray-300'
                  }`}
                >
                  {link.label}
                </Link>
              ) : (
                <span
                  key={index}
                  className="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-300 ring-1 ring-inset ring-gray-300 dark:ring-gray-600 dark:text-gray-500"
                >
                  {link.label}
                </span>
              );
            })}
          </nav>
        </div>
      </div>
    </div>
  );
}