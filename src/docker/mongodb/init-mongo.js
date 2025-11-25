// MongoDB Initialization Script
// Clean Architecture Parking System - NoSQL Database Setup

// Switch to the parking database
db = db.getSiblingDB('parking_db');

print('🔧 Initializing MongoDB for Clean Architecture Parking System...');

// ============================================
// COLLECTIONS CREATION (WITHOUT STRICT VALIDATORS)
// ============================================

// 1. Pricing Grids Collection
db.createCollection('pricing_grids');

// 2. Subscription Time Slots Collection
db.createCollection('subscription_time_slots');

// 3. Invoices Collection
db.createCollection('invoices');

// 4. Parking Events Log Collection
db.createCollection('parking_events');

// 5. System Logs Collection
db.createCollection('system_logs');

// ============================================
// INDEXES
// ============================================

// Pricing Grids indexes
db.pricing_grids.createIndex({ parking_id: 1 }, { unique: true });
db.pricing_grids.createIndex({ updated_at: -1 });

// Subscription Time Slots indexes
db.subscription_time_slots.createIndex({ subscription_id: 1 }, { unique: true });
db.subscription_time_slots.createIndex({ updated_at: -1 });

// Invoices indexes
db.invoices.createIndex({ invoice_id: 1 }, { unique: true });
db.invoices.createIndex({ user_id: 1 });
db.invoices.createIndex({ invoice_number: 1 }, { unique: true });
db.invoices.createIndex({ date: -1 });
db.invoices.createIndex({ payment_status: 1 });

// Parking Events indexes
db.parking_events.createIndex({ timestamp: -1 });
db.parking_events.createIndex({ type: 1 });
db.parking_events.createIndex({ parking_id: 1 });
db.parking_events.createIndex({ user_id: 1 });
db.parking_events.createIndex({ session_id: 1 });

// System Logs indexes
db.system_logs.createIndex({ timestamp: -1 });
db.system_logs.createIndex({ level: 1 });

// ============================================
// SEED DATA
// ============================================

print('📝 Inserting seed data...');

// Default pricing grid
db.pricing_grids.insertOne({
    parking_id: 'default',
    name: 'Default Pricing Grid',
    currency: 'EUR',
    rates: {
        '15min': 0.30,
        '30min': 0.60,
        '1h': 1.20,
        '2h': 3.20,
        '4h': 5.00,
        'day': 12.00,
        'night': 2.00
    },
    special_rates: {
        weekend: {
            day: 10.00
        },
        holiday: {
            day: 8.00
        }
    },
    updated_at: new Date()
});

// Example subscription time slots
db.subscription_time_slots.insertOne({
    subscription_id: 'example-subscription-001',
    time_slots: [
        { day: 'Monday', start: '08:00', end: '18:00' },
        { day: 'Tuesday', start: '08:00', end: '18:00' },
        { day: 'Wednesday', start: '08:00', end: '18:00' },
        { day: 'Thursday', start: '08:00', end: '18:00' },
        { day: 'Friday', start: '08:00', end: '18:00' }
    ],
    timezone: 'Europe/Paris',
    updated_at: new Date()
});

// Example invoice
db.invoices.insertOne({
    invoice_id: 'example-invoice-001',
    user_id: 'example-user-001',
    invoice_number: 'INV-2025-000001',
    date: new Date(),
    items: [
        {
            type: 'parking_session',
            description: 'Parking session - 2 hours',
            quantity: 1,
            unit_price: 3.20,
            total: 3.20
        }
    ],
    subtotal: 3.20,
    tax_rate: 0.20,
    tax_amount: 0.64,
    total: 3.84,
    payment_status: 'paid',
    payment_method: 'credit_card',
    pdf_path: null
});

// Example parking event
db.parking_events.insertOne({
    timestamp: new Date(),
    type: 'entry',
    parking_id: 'example-parking-001',
    user_id: 'example-user-001',
    session_id: 'example-session-001',
    metadata: {
        plate_number: 'AB-123-CD',
        barrier_id: 'barrier_1'
    }
});

// Example system log
db.system_logs.insertOne({
    timestamp: new Date(),
    level: 'info',
    message: 'MongoDB initialized successfully',
    context: {
        collections: ['pricing_grids', 'subscription_time_slots', 'invoices', 'parking_events', 'system_logs']
    }
});

// ============================================
// COMPLETION
// ============================================

print('✅ MongoDB initialization complete!');
print('📊 Collections created: pricing_grids, subscription_time_slots, invoices, parking_events, system_logs');
print('🔑 Indexes applied for performance');
print('🌱 Seed data inserted');
print('🔗 Ready for hybrid architecture with PostgreSQL');
