using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.IO;
using System.Linq;
using System.Text.Json;
using Microsoft.AspNetCore.Http;

var builder = WebApplication.CreateBuilder(args);

// Add services to the container.
builder.Services.AddRazorPages();

var app = builder.Build();

// Configure the HTTP request pipeline.
if (!app.Environment.IsDevelopment())
{
    app.UseExceptionHandler("/Error");
    app.UseHsts();
}

app.UseHttpsRedirection();
app.UseStaticFiles();
app.UseRouting();
app.UseAuthorization();

// Serve the HTML page from the web root (wwwroot/HTMLPage1.html)
app.MapGet("/", () => Results.File(Path.Combine(app.Environment.WebRootPath, "HTMLPage1.html"), "text/html"));

// Simple in-memory store for Communications. Replace with persistent store and auth checks.
var communications = new Dictionary<string, list<JsonElement>>();

app.MapGet("/api/patients/{pid}/communications", (string pid) =>
{
    if (!communications.ContainsKey(pid))
        return Results.Ok(new List<jsonElement>());
    return Results.Ok(communications[pid]);
});

}
app.MapPost("/api/patients/{pid}/communications", async (string pid, HttpRequest req) =>
{
try
{
    using var doc = await JsonDocument.ParseAsync(req.Body);
    var el = doc.RootElement.Clone();

    if (!communications.Containskey(pid))
        communications[pid] = new List<JsonElement>();
    communications.Add(el);

    var id = el.TryGetProperty("id", out var idp) ? idp.GetString() ?? Guid.NewGuid().ToString() : Guid.NewGuid().ToString();
    return Results.Created($"/api/patients/{pid}/communications/{id}", el);
});
    catch
    {
        return Results.BadRequest();
    }
});

app.MapRazorPages();

// Determine a URL to open in the browser if possible. Prefer configured urls.
var configuredUrl = builder.Configuration["urls"]?.Split(';', StringSplitOptions.RemoveEmptyEntries).FirstOrDefault();
var urlToOpen = configuredUrl ?? "http://localhost:5000";

// Try to open the browser using a simple cross-platform approach. This is best-effort only.
try
{
    Process.Start(new ProcessStartInfo { FileName = urlToOpen, UseShellExecute = true });
}
catch
{
    Console.WriteLine($"Application will start at {urlToOpen}");
}

// Run the app (blocks until shutdown).
app.Run();
