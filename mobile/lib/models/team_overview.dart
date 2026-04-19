class TeamOverview {
  TeamOverview({
    required this.items,
    required this.currentPage,
    required this.perPage,
    required this.total,
  });

  final List<TeamOverviewItem> items;
  final int currentPage;
  final int perPage;
  final int total;

  factory TeamOverview.fromJson(Map<String, dynamic> json) {
    final items = (json['items'] as List? ?? const [])
        .whereType<Map>()
        .map((item) => TeamOverviewItem.fromJson(item.cast<String, dynamic>()))
        .toList();

    final meta = (json['meta'] as Map?)?.cast<String, dynamic>() ?? const <String, dynamic>{};

    return TeamOverview(
      items: items,
      currentPage: (meta['current_page'] ?? 1) as int,
      perPage: (meta['per_page'] ?? items.length) as int,
      total: (meta['total'] ?? items.length) as int,
    );
  }
}

class TeamOverviewItem {
  TeamOverviewItem({
    required this.employeeId,
    required this.name,
    required this.role,
    required this.managerRole,
    required this.checkedIn,
    required this.checkInTime,
    required this.checkOutTime,
    required this.hoursWorked,
    required this.overtimeHours,
    required this.estimatedGain,
    required this.currency,
    required this.status,
  });

  final int employeeId;
  final String name;
  final String? role;
  final String? managerRole;
  final bool checkedIn;
  final String? checkInTime;
  final String? checkOutTime;
  final double hoursWorked;
  final double overtimeHours;
  final double estimatedGain;
  final String currency;
  final String status;

  factory TeamOverviewItem.fromJson(Map<String, dynamic> json) {
    return TeamOverviewItem(
      employeeId: (json['employee_id'] ?? 0) as int,
      name: (json['name'] ?? '') as String,
      role: json['role'] as String?,
      managerRole: json['manager_role'] as String?,
      checkedIn: json['checked_in'] == true,
      checkInTime: json['check_in_time'] as String?,
      checkOutTime: json['check_out_time'] as String?,
      hoursWorked: double.tryParse((json['hours_worked'] ?? 0).toString()) ?? 0,
      overtimeHours: double.tryParse((json['overtime_hours'] ?? 0).toString()) ?? 0,
      estimatedGain: double.tryParse((json['estimated_gain'] ?? 0).toString()) ?? 0,
      currency: (json['currency'] ?? 'DA') as String,
      status: (json['status'] ?? 'absent') as String,
    );
  }
}
