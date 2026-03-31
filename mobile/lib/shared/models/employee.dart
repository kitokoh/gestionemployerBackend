// mobile/lib/shared/models/employee.dart

enum EmployeeRole { employee, manager, superAdmin }
enum EmployeeStatus { active, inactive, archived }
enum ManagerRole { principal, rh, dept, comptable, superviseur }

class EmployeeCompany {
  final String id;
  final String name;
  final String language;
  final String timezone;
  final String currency;
  final String? logoUrl;

  const EmployeeCompany({
    required this.id,
    required this.name,
    required this.language,
    required this.timezone,
    required this.currency,
    this.logoUrl,
  });

  factory EmployeeCompany.fromJson(Map<String, dynamic> json) => EmployeeCompany(
    id: json['id'] as String,
    name: json['name'] as String,
    language: json['language'] as String,
    timezone: json['timezone'] as String,
    currency: json['currency'] as String,
    logoUrl: json['logo_url'] as String?,
  );
}

class Department {
  final int id;
  final String name;
  const Department({required this.id, required this.name});
  factory Department.fromJson(Map<String, dynamic> json) =>
      Department(id: json['id'] as int, name: json['name'] as String);
}

class Position {
  final int id;
  final String name;
  const Position({required this.id, required this.name});
  factory Position.fromJson(Map<String, dynamic> json) =>
      Position(id: json['id'] as int, name: json['name'] as String);
}

class Employee {
  final int id;
  final String matricule;
  final String? zktecoId;
  final String firstName;
  final String lastName;
  final String email;
  final String? phone;
  final String role;
  final String? managerRole;
  final Department? department;
  final Position? position;
  final double leaveBalance;
  final String status;
  final String? photoUrl;
  final String? hireDate;
  final EmployeeCompany? company;

  const Employee({
    required this.id,
    required this.matricule,
    this.zktecoId,
    required this.firstName,
    required this.lastName,
    required this.email,
    this.phone,
    required this.role,
    this.managerRole,
    this.department,
    this.position,
    required this.leaveBalance,
    required this.status,
    this.photoUrl,
    this.hireDate,
    this.company,
  });

  factory Employee.fromJson(Map<String, dynamic> json) => Employee(
    id: json['id'] as int,
    matricule: json['matricule'] as String,
    zktecoId: json['zkteco_id'] as String?,
    firstName: json['first_name'] as String,
    lastName: json['last_name'] as String,
    email: json['email'] as String,
    phone: json['phone'] as String?,
    role: json['role'] as String,
    managerRole: json['manager_role'] as String?,
    department: json['department'] != null
        ? Department.fromJson(json['department'] as Map<String, dynamic>)
        : null,
    position: json['position'] != null
        ? Position.fromJson(json['position'] as Map<String, dynamic>)
        : null,
    leaveBalance: (json['leave_balance'] as num).toDouble(),
    status: json['status'] as String,
    photoUrl: json['photo_url'] as String?,
    hireDate: json['hire_date'] as String?,
    company: json['company'] != null
        ? EmployeeCompany.fromJson(json['company'] as Map<String, dynamic>)
        : null,
  );

  Map<String, dynamic> toJson() => {
    'id': id,
    'matricule': matricule,
    'first_name': firstName,
    'last_name': lastName,
    'email': email,
    'role': role,
    'leave_balance': leaveBalance,
    'status': status,
  };

  String get fullName => '$firstName $lastName';
  bool get isManager => role == 'manager';
  bool get isEmployee => role == 'employee';

  Employee copyWith({
    String? firstName,
    String? lastName,
    String? phone,
    String? photoUrl,
    double? leaveBalance,
    String? status,
  }) => Employee(
    id: id,
    matricule: matricule,
    firstName: firstName ?? this.firstName,
    lastName: lastName ?? this.lastName,
    email: email,
    phone: phone ?? this.phone,
    role: role,
    managerRole: managerRole,
    department: department,
    position: position,
    leaveBalance: leaveBalance ?? this.leaveBalance,
    status: status ?? this.status,
    photoUrl: photoUrl ?? this.photoUrl,
    hireDate: hireDate,
    company: company,
  );
}
