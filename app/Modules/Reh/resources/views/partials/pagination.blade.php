@if($paginator->hasPages())
<nav class="reh-pagination" aria-label="صفحات النتائج"><button type="button" class="btn btn-sm btn-light" data-page="{{ $paginator->currentPage() - 1 }}" @disabled($paginator->onFirstPage())>السابق</button><span>صفحة {{ $paginator->currentPage() }} من {{ $paginator->lastPage() }}</span><button type="button" class="btn btn-sm btn-light" data-page="{{ $paginator->currentPage() + 1 }}" @disabled(!$paginator->hasMorePages())>التالي</button></nav>
@endif
