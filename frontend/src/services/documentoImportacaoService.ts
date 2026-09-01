import { api } from '@/services/api'

import type {
  ConfirmarImportacaoPayload,
  DocumentoImportacaoPreview,
  ImportacaoConfirmada,
} from '@/types/DocumentoImportacao'

interface PreviewResponse {
  data: DocumentoImportacaoPreview
}

interface ConfirmarResponse {
  data: ImportacaoConfirmada
}

export const documentoImportacaoService = {
  async gerarPreview(
    arquivos: File[],
  ): Promise<DocumentoImportacaoPreview> {
    const formData = new FormData()

    arquivos.forEach((arquivo) => {
      formData.append('arquivos[]', arquivo)
    })

    const response =
      await api.post<PreviewResponse>(
        '/api/documentos/importar-preview',
        formData,
      )

    return response.data.data
  },

  async confirmar(
    documentoId: number,
    dados: ConfirmarImportacaoPayload,
  ): Promise<ImportacaoConfirmada> {
    const response =
      await api.post<ConfirmarResponse>(
        `/api/documentos/${documentoId}/confirmar`,
        dados,
      )

    return response.data.data
  },
}
