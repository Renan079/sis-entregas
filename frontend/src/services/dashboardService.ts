import { api } from '@/services/api'

import type {
  DashboardData,
} from '@/types/Dashboard'

interface DashboardResponse {
  data: DashboardData
}

export const dashboardService = {
  async buscar(): Promise<DashboardData> {
    const response =
      await api.get<DashboardResponse>(
        '/api/dashboard',
      )

    return response.data.data
  },
}
