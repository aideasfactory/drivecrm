/** Data Import page: a run (check or import) as polled from /imports/runs/{id}. */

export interface ImportCheckError {
    file: string
    line: number
    message: string
}

export interface ImportFileCheck {
    rows: number
    already_imported: number
    new: number
    skipped: Record<string, number> | []
    notes: Record<string, number> | []
}

export interface ImportCheckTotals {
    valid: boolean
    manifest: { present: boolean; ok: boolean; problems: string[] }
    check: {
        files: Record<string, ImportFileCheck>
        errors: ImportCheckError[]
        error_count: number
    }
}

export interface ImportRun {
    id: number
    status: 'queued' | 'running' | 'completed' | 'failed'
    dry_run: boolean
    stage: string | null
    file_name: string | null
    totals: ImportCheckTotals | null
    stage_totals: Record<string, Record<string, number>> | null
    error: string | null
}
