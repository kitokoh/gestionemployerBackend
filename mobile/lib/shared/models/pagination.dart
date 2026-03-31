// mobile/lib/shared/models/pagination.dart

class PaginationMeta {
  final int currentPage;
  final int lastPage;
  final int perPage;
  final int total;

  const PaginationMeta({
    required this.currentPage,
    required this.lastPage,
    required this.perPage,
    required this.total,
  });

  factory PaginationMeta.fromJson(Map<String, dynamic> json) => PaginationMeta(
    currentPage: json['current_page'] as int,
    lastPage: json['last_page'] as int,
    perPage: json['per_page'] as int,
    total: json['total'] as int,
  );

  bool get hasNextPage => currentPage < lastPage;
  bool get hasPreviousPage => currentPage > 1;
}

class PaginatedResult<T> {
  final List<T> data;
  final PaginationMeta meta;

  const PaginatedResult({required this.data, required this.meta});
}
